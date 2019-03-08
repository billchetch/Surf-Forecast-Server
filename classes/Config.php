<?php
class Config{
	private static $config = array();
	private static $keys = array();
	
	public static function initialise(){
			
	}
	
	public static function set($key, $val){
		$key = strtoupper($key);
		self::$config[$key] = $val;
		if(!in_array($key, self::$keys))array_push(self::$keys, $key);
	}
	public static function get($key, $default = null){
		return self::has($key) ? self::$config[$key] : $default;
	}
	public static function getAsArray($key, $default = null, $delimiter = ','){
		$val = self::get($key, $default);
		if(is_array($val))return $val;
		if(is_string($val))return explode($delimiter, $val);
		return null;
	}
	public static function has($key){
		return in_array($key, self::$keys);
	}
	
	public static function replace($str){
		return self::replaceKeysWithValues($str, self::$config);
	}
	
	public static function replaceKeysWithValues($str, $keyValuePairs){
		foreach($keyValuePairs as $key=>$val){
			if(!is_string($val))continue;
			$str = str_ireplace('{'.strtoupper($key).'}', $val, $str);
		}
		return $str;
	}
}
?>