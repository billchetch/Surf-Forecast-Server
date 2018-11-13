<?php
class FeedResult extends DBObject{
	
	public static $config = array();
	
	public $parser;
	public $source;
	public $sourceID;
	public $locationID;
	public $response;
	public $responseInfo;
	public $forecastData;
	
	/*
	 * static methods
	 */
	
	public static function initialise(){
		static::$config['TABLE_NAME'] = Config::get('FEED_RESULTS_TABLE');
		
		$sql = "SELECT r.*, f.source_id, f.location_id, f.response_format, s.source, s.parser, l.timezone ";
		$rtbl = static::$config['TABLE_NAME'];
		$ftbl = Config::get('FEEDS_TABLE');
		$stbl = Config::get('SOURCES_TABLE');
		$ltbl = Config::get('LOCATIONS_TABLE');
		$sql.= "FROM $rtbl r INNER JOIN $ftbl f ON r.feed_id=f.id INNER JOIN $stbl s ON f.source_id=s.id INNER JOIN $ltbl l ON f.location_id=l.id ";
		$sql.= "WHERE r.parsed=0";
		static::$config['SELECT_ROWS_SQL'] = $sql;	
	}
	
	/*
	 * Local methods
	 */
	
	public function __construct($rowdata, $readFromDB = self::READ_MISSING_VALUES_ONLY){
		parent::__construct($rowdata, $readFromDB);
		
		if(!empty($this->rowdata['source'])){
			if(empty($this->rowdata['parser'])){
				$cls = ucwords(str_replace(' ', '', $this->rowdata['source'])).'Parser';
			} else {
				$cls = $this->rowdata['parser'];
			}
			if(empty($cls))throw new Exception("No parser class found");
			if(!class_exists($cls))throw new Exception("Class $cls is not found");
			 
			eval('$this->parser = new '.$cls.'();');
		}
		
		$this->assignR2V($this->response, 'response');
		$this->assignR2V($this->responseFormat, 'response_format');
		$this->assignR2V($this->source, 'source');
		$this->assignR2V($this->sourceID, 'source_id');
		$this->assignR2V($this->locationID, 'location_id');
		$this->assignR2V($this->timezone, 'timezone');
		$this->assignR2V($this->responseInfo, 'response_info');
		if(!empty($this->responseInfo)){
			try{
				$this->responseInfo = json_decode($this->responseInfo, true);
			} catch (Exception $e){
				//fail silently
			}
		}
	}
	
	public function parse(){
		if(empty($this->parser))throw new Exception("No parser class set");
		
		//create forecast record and record that this has been successfully parsed and stored
		$this->forecastData = $this->parser->parse($this);
		if($this->forecastData){
			$this->forecastData['location_id'] = $this->locationID;
			$this->forecastData['source_id'] = $this->sourceID;
		}
		
		return $this->forecastData;
		
	}
}
?>