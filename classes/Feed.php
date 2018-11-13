<?php
class Feed extends DBObject{
	
	public static $config = array();
	
	public $source;
	public $location;
	public $url;
	public $data;
	public $info;
	public $error;
	public $errno;
	
	/*
	 * static methods
	 */
	public static function initialise(){
		static::$config['TABLE_NAME'] = Config::get('FEEDS_TABLE');
		
		//base SQL
		$ftbl = static::$config['TABLE_NAME'];
		$stbl = Config::get('SOURCES_TABLE');
		$ltbl = Config::get('LOCATIONS_TABLE');
		$sql = "SELECT f.*, s.source, s.api_key, l.location, l.latitude, l.longitude, concat(s.base_url, IF(f.endpoint IS NOT NULL, concat('/', f.endpoint), ''), IF(f.querystring IS NOT NULL, concat('?', f.querystring), '')) AS url ";
		$sql.= "FROM $ftbl f INNER JOIN $stbl s ON f.source_id=s.id INNER JOIN $ltbl l ON f.location_id=l.id ";
		
		//single row
		static::$config['SELECT_ROW_BY_ID_SQL'] = $sql." WHERE f.source_id=:source_id AND f.id=:id";
		static::$config['SELECT_ROW_SQL'] = $sql." WHERE f.source_id=:source_id AND f.location_id=:location_id";
		
		//collection
		static::$config['SELECT_ROWS_SQL'] = $sql." WHERE f.active=1 AND s.active = 1 AND l.active=1 AND l.forecast_location_id IS NULL ORDER BY location"; 
	}
	
	
	
	/*
	 * Instance methods
	 */
	public function __construct($rowdata, $readFromDB = self::READ_MISSING_VALUES_ONLY){
		parent::__construct($rowdata, $readFromDB);
		
		$this->assignR2V($this->source, 'source');
		$this->assignR2V($this->location, 'location');
			
		if($this->id && $readFromDB){
			if(empty($this->rowdata['url']))throw new Exception("No URL supplied for feed");
		}
		if(!empty($this->rowdata) && !empty($this->rowdata['url'])){
			$url = Config::replace($this->rowdata['url']); //pre-defined replacements
			$url = Config::replaceKeysWithValues($url, $this->rowdata);
			$this->url = $url;
		} 
		
	}
	
	
	public function download(){
		//retrieve data
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url); 
	    curl_setopt($ch, CURLOPT_HEADER, false); 
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    if($this->rowdata['encoding'])curl_setopt($ch, CURLOPT_ENCODING, $this->rowdata['encoding']);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, Config::get('CURLOPT_CONNECTTIMEOUT',30));
		curl_setopt($ch, CURLOPT_TIMEOUT, Config::get('CURLOPT_TIMEOUT',30));
	    $this->data = curl_exec($ch); 
	    $this->error = curl_error($ch);
	    $this->errno = curl_errno($ch);
	    $this->info = curl_getinfo($ch);
	    curl_close($ch);
				
        //store stuff if it's any good
        if($this->data && $this->errno == 0 && $this->info['http_code'] < 400){
        	return 1;
        } else {
        	return 0;
        }
	}
	
	public function getFeedResultValues(){
		$vals = array();
	    $vals['feed_id'] = $this->id;
	    $vals['response_info'] = json_encode($this->info);
	    $vals['response'] = $this->data;
	    $vals['errno'] = $this->errno;
	    $vals['error'] = $this->error;
	    return $vals;
	}
}
?>