<?php
class Location extends \chetch\db\DBObject{
	public static function initialise(){
		$t = \chetch\Config::get("LOCATIONS_TABLE", 'locations');
		self::setConfig('TABLE_NAME', $t);
		$sql = "SELECT * FROM $t";
		self::setConfig('SELECT_SQL', $sql);
		self::setConfig('SELECT_DEFAULT_FILTER', 'active=1');
	}
}
?>