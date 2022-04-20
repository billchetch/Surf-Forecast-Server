<?php
class Sources extends \chetch\db\DBObject{
	public static function initialise(){
		$t = \chetch\Config::get("SOURCES_TABLE", 'sf_sources');
		self::setConfig('TABLE_NAME', $t);
		$sql = "SELECT * FROM $t";
		self::setConfig('SELECT_SQL', $sql);
		self::setConfig('SELECT_DEFAULT_FILTER', 'active=1');
	}
}
?>