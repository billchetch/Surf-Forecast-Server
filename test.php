<?php
require('_include.php');

function convertNumber($val, $round = -1){  //scientific exponent
		//e.g. 0.1908054709434509E+1
		$val2return = null;
		$pos = stripos($val, 'E');
		if($pos !== false){
			$n = substr($val, 0, $pos);
			$x = substr($val, $pos);
			$ar = null;
			if(stripos($x,'+') !== false){
				$ar = explode('+', $x);
			} elseif(stripos($x,'-') !== false){
				$ar = explode('-', $x);
				if(count($ar) == 2)$ar[1] = -$ar[1];
			}
			if(!$ar || count($ar) != 2)throw new Exception("Cannot parse exponent $x");
			$val2return = $n * pow(10, $ar[1]);
		} else {
			$val2return = $val;
		}
		if($round > 0 && $val2return != null)$val2return = round($val2return, $round);
		return $val2return;
	}

//init logger
Logger::init($dbh, array('log_name'=>'test', 'log_options'=>Logger::LOG_TO_SCREEN));
$router = null;
try{
	$errors = array();
	$feeds = Feed::createCollection($dbh);
	$sourceForTideInfo = Config::get('TIDE_INFO_SOURCE_ID', 4);
	$source = Sources::createInstanceFromID($dbh, $sourceForTideInfo);
	
	$feeds2download = array();
	foreach($feeds as $feed){
		if($feed->rowdata['source_id'] == $sourceForTideInfo){
			array_push($feeds2download, $feed);
		}
	}
	
	foreach($feeds2download as $feed){
		$feed->url.= "&datums";
		if($feed->download()){
			$result = $feed->getFeedResultValues();
			$data = json_decode($result['response'], true);
			if(json_last_error()){
				array_push($errors, json_last_error());
				continue;
			}
			if(!isset($data['datums'])){
				array_push($errors, "datums property not found");
				continue;
			}
			foreach($data['datums'] as $datum){
				if($datum['name'] == 'HAT'){
					$locationID = $feed->rowdata['location_id'];
					$location  = Location::createInstanceFromID($dbh, $locationID);
					$location->rowdata['max_tidal_variation'] = $datum['height'];
					$location->write(); 
				}
			} //end datums loop
		} 
	} //end download loop
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>