<?php
class Sources extends DBObject{
	static public $config = array();
	
	public static function initialise(){
		$t = Config::get("SOURCES_TABLE");
		static::$config['TABLE_NAME'] = $t;
		$sql = "SELECT * FROM $t s WHERE s.active=1";
		static::$config['SELECT_ROWS_SQL'] = $sql;
	}
}
?>