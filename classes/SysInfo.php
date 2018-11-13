<?php
class SysInfo extends DBObject{
	public static $config = array();
	
	public static function initialise(){
		$t = Config::get('SYS_INFO_TABLE');
		
		static::$config['TABLE_NAME'] = $t;
		static::$config['SELECT_ROW_SQL'] = "SELECT * FROM $t WHERE data_name=:data_name LIMIT 1";
	}
	
	
	public static function set($dataName, $dataValue){
		$si = self::createInstance(self::$dbh, array('data_name'=>$dataName));
		if(is_array($dataValue)){
			$dataValue = json_encode($dataValue);
			$si->rowdata['encoded'] = 1;	
		} else {
			$si->rowdata['encoded'] = 0;
		}
		if(empty($si->rowdata['data_name']))throw new Exception("No data_name supplied");
		$si->rowdata['data_value'] = $dataValue;
		$si->rowdata['updated'] = self::now();
		
		$si->write();
	}
	
	public static function get($dataName){
		$si = self::createInstance(self::$dbh, array('data_name'=>$dataName));
		
		if(!empty($si->rowdata['encoded'])){
			return json_decode($si->rowdata['data_value'], true);
		} else {
			return empty($si->rowdata['data_value']) ? null : $si->rowdata['data_value'];
		}
	}
	
	public static function clear($dataName, $delete = false){
		if($delete){
			//TODO: delete record
		} else {
			self::set($dataName, null);
		}
	}
}