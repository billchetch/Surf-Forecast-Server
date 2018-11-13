<?php
class Digest extends DBObject{
	const STATUS_OUTSTANDING = 0;
	const STATUS_EMAILED = 1;
	const STATUS_POSTED = 2;
	const STATUS_RECEIVED = 4;
	const STATUS_RECEIVED_AND_EMAILED = 5;
	
	public static $config = array();
	
	private $digestInfo = array();
	
	public static function initialise(){
		$t = Config::get('SYS_DIGESTS_TABLE');
		static::$config['TABLE_NAME'] = $t;
		static::$config['LINE_FEED'] = Config::get('DIGEST_LINE_FEED', "\n");
		
		//collection
		$sql = "SELECT *,CONVERT_TZ(created, @@session.time_zone, '+00:00') AS utc_created FROM $t";
		static::$config['SELECT_ROW_BY_ID_SQL'] = $sql." WHERE id=:id";
		
		static::$config['SELECT_ROWS_SQL'] = $sql." WHERE status=:status ORDER BY IF(source_created IS NULL,created,source_created)";
	}
	
	public static function create($dbh, $title){
		$params = array();
		$params['digest_title'] = $title;
		return parent::createInstance($dbh, $params, false);
	}
	
	public static function getStatus($status){
		$params = array();
		$params['status'] = $status;
		$digests = self::createCollection(self::$dbh, $params);
		return $digests;
	}
	
	public static function getOutstanding(){
		return self::getStatus(self::STATUS_OUTSTANDING);
	}
	
	public static function getReceived(){
		return self::getStatus(self::STATUS_RECEIVED);
	}
	
	public static function addDigest($dbh, $params){
		$digest = parent::createInstance($dbh, $params, false);
		$digest->write();
		return $digest;
	}
	
	public static function formatAssocArray($ar, $delimiter = null){
		if(!$delimiter)$delimiter = static::$config['LINE_FEED'];
		$s = '';
		foreach($ar as $k=>$v){
			$s.= ($s ? $delimiter : '').("$k: $v");
		}
		return $s;
	}
	
	public function addDigestInfo($area, $info, $lfcount = 2){
		if(!isset($this->digestInfo[$area])){
			$this->digestInfo[$area] = $info;
		} else {
			$lf = static::$config['LINE_FEED'];
			$this->digestInfo[$area].= str_repeat($lf, $lfcount).$info;
		}
	}
	
	public function getDigestInfo($area = null){
		$s = '';
		if($area){
			return isset($this->digestInfo[$area]) ? $this->digestInfo[$area] : ''; 
		} else {
			$lf = static::$config['LINE_FEED']; 
			foreach($this->digestInfo as $area=>$info){
				$s.= $area.$lf.$lf;
				$s.= $info.$lf.$lf;
			}
		}
		return $s;
	}
	
	public function setStatus($status){
		$this->rowdata['status'] = $status;
	}
	
	public function write(){
		if(empty($this->rowdata['digest']) && !empty($this->digestInfo)){
			$this->rowdata['digest'] = $this->getDigestInfo();
		}
		return parent::write();
	}
	
	public function getPostData(){
		if($this->id && !isset($this->rowdata['created'])){
			$this->read();
		}
		$data = $this->rowdata;
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