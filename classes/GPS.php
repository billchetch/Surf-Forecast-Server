<?php
class GPS extends DBObject{
	public static $config = array();

	public static function initialise(){
		$t = Config::get('GPS_TABLE');
		static::$config['TABLE_NAME'] = $t;
		
		$sql = "SELECT *, now()-created AS secs FROM $t";
		static::$config['SELECT_ROWS_SQL'] = "$sql WHERE now()-created<:secs ORDER BY created DESC";
	}
	
	public static function addCoords($coords){
		$gps = self::createInstance(self::$dbh, null, false);
		$ar = array('latitude'=>null, 'longitude'=>null, 'location_accuracy'=>null);
		foreach($ar as $f=>$v)$ar[$f] = $coords[$f];
		$gps->rowdata = $ar;
		$gps->write();
		return $gps;
	}
	
	public static function getRecent($secs = 3){
		$ar = self::createCollection(self::$dbh, array('secs'=>$secs));
		return $ar;
	}
	
	public function getSummary(){
		$dt = $this->rowdata['created'];
		return $dt.': '.$this->rowdata['latitude'].','.$this->rowdata['longitude'];
	}
	
}