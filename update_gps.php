<?php
require('_include.php');

Logger::init($dbh, array('log_name'=>'update gps', 'log_options'=>Logger::LOG_TO_SCREEN));

try{
	$dbh->query('SET time_zone = "+00:00"'); //UTC for all
	
	$gpsdb = Config::get('GPS_DBNAME', 'gps');
	
	//check status of device
	Logger::info("Checking status of device...");
	$t = $gpsdb.'.sys_info';
	$sql = "SELECT * FROM $t WHERE data_name='gps_device_status'";
	$q = $dbh->query($sql);
	$row = $q->fetch();
	if(!$row){
		Logger::warning("No GPS device status found");
		die;
	} else {
		$data = json_decode($row['data_value'], true);
		if($data['status'] != 'recording'){
			Logger::warning("Script aborting because GPS device is not recording, it is of status ".$data['status']." ".$data['message']);
			die;
		} else {
			Logger::info("Device is currently recording");
		}
	}

	//we know the devices is functioning
	Logger::info("Calculating average of recent GPS positions");
	$t = $gpsdb.'.gps_positions'; 
	$sql = "SELECT * FROM $t ORDER BY updated DESC LIMIT 3";
	$q = $dbh->query($sql);
	$avg = array('latitude'=>0, 'longitude'=>0, 'accuracy'=>0, 'updated'=>0);
	$rowCount = 0;
	while($row = $q->fetch()){
		foreach($avg as $k=>$v){
			$avg[$k] += $k == 'updated' ? strtotime($row[$k]) : $row[$k];	
		}
		$rowCount++;
	}
	if($rowCount > 0){
		Logger::info("Calculated average and writing to GPS history");
		foreach($avg as $k=>$v){
			$avg[$k] = $v/$rowCount;
		}
		$avg['location_accuracy'] = $avg['accuracy'];
		
		GPS::init($dbh);
		GPS::addCoords($avg);
	}
	
	//save digest
	$dt = SysInfo::get('last_gps_digest');
	if(!$dt || (time() - strtotime($dt) > Config::get('DIGEST_FROM_GPS_TIME', 60*60*1))){
		$digest = Digest::create($dbh, "GPS");
		
		$t = $t = $gpsdb.'.gps_positions';
		$sql = "SELECT * FROM $t ORDER BY updated DESC LIMIT 10";
		$q = $dbh->query($sql);
		while($row = $q->fetch(PDO::FETCH_ASSOC)){
			$digest->addDigestInfo('GPS POSITIONS', $digest->formatAssocArray($row, ','), 1);
		}
		
		$t = $t = $gpsdb.'.gps_satellites';
		$sql = "SELECT * FROM $t ORDER BY updated DESC LIMIT 10";
		$q = $dbh->query($sql);
		while($row = $q->fetch(PDO::FETCH_ASSOC)){
			$digest->addDigestInfo('GPS SATELLITES', $digest->formatAssocArray($row, ','), 1);
		}
		
		SysInfo::set('last_gps_digest', date('Y-m-d H:i:s'));
		$digest->write();
		Logger::info("Saving digest");	
	} else {
		Logger::info("Abandoning digest as too recent since last digest");
	}
	
} catch (Exception $e){
	Logger::exception($e->getMessage());
}
?>