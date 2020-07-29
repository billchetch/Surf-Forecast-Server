<?php
use \chetch\Config as Config;

class Digest extends chetch\db\DBObject{
	const STATUS_OUTSTANDING = 0;
	const STATUS_EMAILED = 1;
	const STATUS_POSTED = 2;
	const STATUS_RECEIVED = 4;
	const STATUS_RECEIVED_AND_EMAILED = 5;
	
	private $digestInfo = array();
	
	public static function initialise(){
		$t = Config::get('SYS_DIGESTS_TABLE', 'digests');
		static::setConfig('TABLE_NAME', $t);
		static::setConfig('LINE_FEED', Config::get('DIGEST_LINE_FEED', "\n"));
		
		//collection
		$sql = "SELECT *, CONVERT_TZ(created, @@session.time_zone, '+00:00') AS utc_created FROM $t";
		static::setConfig('SELECT_SQL', $sql);
		static::setConfig('SELECT_DEFAULT_FILTER', "status=:status");
		static::setConfig('SELECT_DEFAULT_SORT', "IF(source_created IS NULL,created,source_created)");
	}
	
	public static function create($title){
		$params = array();
		$params['digest_title'] = $title;
		return parent::createInstance($params, false);
	}
	
	public static function getStatus($status){
		$params = array();
		$params['status'] = $status;
		$digests = self::createCollection($params);
		return $digests;
	}
	
	public static function getOutstanding(){
		return self::getStatus(self::STATUS_OUTSTANDING);
	}
	
	public static function getReceived(){
		return self::getStatus(self::STATUS_RECEIVED);
	}
	
	public static function addDigest($params){
		$digest = parent::createInstance($params, false);
		$digest->write();
		return $digest;
	}
	
	public static function formatAssocArray($ar, $delimiter = null){
		if(!$delimiter)$delimiter = static::getConfig('LINE_FEED');
		$s = '';
		foreach($ar as $k=>$v){
			if(is_array($v)){
				$v = static::formatAssocArray($v, $delimiter);
			}
			$s.= ($s ? $delimiter : '').("$k: $v");
		}
		return $s;
	}
	
	public function addDigestInfo($area, $info, $lfcount = 2){
		if(!isset($this->digestInfo[$area])){
			$this->digestInfo[$area] = $info;
		} else {
			$lf = static::getConfig('LINE_FEED');
			$this->digestInfo[$area].= str_repeat($lf, $lfcount).$info;
		}
	}
	
	public function getDigestInfo($area = null){
		$s = '';
		if($area){
			return isset($this->digestInfo[$area]) ? $this->digestInfo[$area] : ''; 
		} else {
			$lf = static::getConfig('LINE_FEED'); 
			foreach($this->digestInfo as $area=>$info){
				$s.= $area.$lf.$lf;
				$s.= $info.$lf.$lf;
			}
		}
		return $s;
	}
	
	public function setStatus($status){
		$this->set('status', $status);
	}
	
	public function write($readAgain = false){
		if(!$this->get('digest') && !empty($this->digestInfo)){
			$this->set('digest', $this->getDigestInfo());
		}
		return parent::write();
	}
	
	public function getPostData(){
		if($this->id && !$this->get('created')){
			$this->read();
		}
		$data = $this->getRowData();
		//$data['source_created'] = $data['utc_created'];
		$data['source_created'] = $data['created'];
		$data['source_timezone_offset'] = $this->tzoffset();
		$data['source_id'] = $data['id'];
		unset($data['id']);
		unset($data['created']);
		unset($data['utc_created']);
		return $data;
	}
}