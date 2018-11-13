<?php
class Logger extends DBObject{
	const LOG_TO_SCREEN = 1;
	const LOG_TO_DATABASE = 2;
	
	public static $config = array();
	public static $log;
	
	
	public $logName;
	public $logOptions;
	
	public static function initialise(){
		static::$config['TABLE_NAME'] = Config::get('SYS_LOGS_TABLE');
	}
	
	public static function init($dbh, $params = null){
		$log = static::createInstance($dbh, null, false);
		
		$log->logName = $params['log_name'];
		$log->logOptions = empty($params['log_options']) ? self::LOG_TO_SCREEN : $params['log_options'];
		
		static::$log = $log;
	}
	
	public static function start(){
		$entry = "Starting ".static::$log->logName." at ".self::now().' '.self::tzoffset();
		static::info($entry);
	}
	public static function info($entry){
		$data = array();
		$data['log_entry_type'] = 'INFO';
		$data['log_entry'] = $entry;
		
		static::$log->logData($data);
	}
	
	public static function warning($entry){
		$data = array();
		$data['log_entry_type'] = 'WARNING';
		$data['log_entry'] = $entry;
		
		static::$log->logData($data);
	}
	
	public static function exception($entry){
		$data = array();
		$data['log_entry_type'] = 'EXCEPTION';
		$data['log_entry'] = $entry;
		
		static::$log->logData($data);
	}
	
	public function logData($data){
		
		$data['log_name'] = $this->logName;
		
		if(($this->logOptions & self::LOG_TO_SCREEN)){
			echo $data['log_entry_type'].': '.$data['log_entry']."\n";
		}
		
		if(($this->logOptions & self::LOG_TO_DATABASE)){
			$this->setRowData($data);
			$this->write();
			unset($this->id); //so the next write an insert and not an update
			$this->rowdata = array();
		}
	}
	
}
?>