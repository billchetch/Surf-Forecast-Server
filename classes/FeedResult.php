<?php
use \chetch\Config as Config;

class FeedResult extends chetch\db\DBObject{
	
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
		$rtbl = Config::get('FEED_RESULTS_TABLE', 'feed_results');
		static::setConfig('TABLE_NAME', $rtbl);
		
		$sql = "SELECT r.*, f.source_id, f.location_id, f.response_format, s.source, s.parser, l.timezone ";
		$ftbl = Config::get('FEEDS_TABLE', 'feeds');
		$stbl = Config::get('SOURCES_TABLE', 'sources');
		$ltbl = Config::get('LOCATIONS_TABLE', 'locations');
		$sql.= "FROM $rtbl r INNER JOIN $ftbl f ON r.feed_id=f.id INNER JOIN $stbl s ON f.source_id=s.id INNER JOIN $ltbl l ON f.location_id=l.id ";
		static::setConfig('SELECT_SQL', $sql);
		static::setConfig('SELECT_DEFAULT_FILTER', 'r.parsed=0');
	}
	
	public static function getAlreadyParsed($days){
		$filter = "parsed=1 AND DATEDIFF(now(), parsed_on)>$days";
		$results = static::createCollection(null, $filter); // array('SQL'=>$sql));
		return $results;
	}
	
	/*
	 * Instance methods
	 */
	
	public function reqd($requireExistence = false){
		parent::read($requireExistence);
		
		if(!empty($this->get('source'))){
			if(empty($this->get('parser'))){
				$cls = ucwords(str_replace(' ', '', $this->get('source'))).'Parser';
			} else {
				$cls = $this->get('parser');
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