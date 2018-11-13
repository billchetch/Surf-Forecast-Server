<?php
class Location extends DBObject{
	static public $config = array();
	
	public static function initialise(){
		$t = Config::get("LOCATIONS_TABLE");
		static::$config['TABLE_NAME'] = $t;
		$sql = "SELECT * FROM $t l WHERE l.active=1";
		static::$config['SELECT_ROWS_SQL'] = $sql;
	}
}
