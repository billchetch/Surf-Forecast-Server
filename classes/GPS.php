<?php
class GPS extends DBObject{
	public static $config = array();

	public $latitude;
	public $longitude;
	public $locationAccuracy;
	
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
	
	public static function getLatest(){
		$t = static::$config['TABLE_NAME'];
		$sql = "SELECT * FROM $t ORDER BY created DESC limit 1";
		$ar = self::createCollection(self::$dbh, array('SQL'=>$sql));
		return count($ar) ? $ar[0] : null;
	}
	
	public function getSummary(){
		$dt = $this->rowdata['created'];
		return $dt.': '.$this->rowdata['latitude'].','.$this->rowdata['longitude'];
	}
	
	public function __construct($rowdata, $readFromDB = self::READ_MISSING_VALUES_ONLY){
		parent::__construct($rowdata, $readFromDB);
		
		$this->assignR2V($this->latitude, 'latitude');
		$this->assignR2V($this->longitude, 'longitude');
		$this->assignR2V($this->locationAccuracy, 'location_accuracy');
	}
	
}