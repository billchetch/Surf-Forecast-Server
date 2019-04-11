<?php
class APIRequest extends DBObject{
	static public $config = array();
	static public $source;
	
	const SOURCE_CACHE = 1;
	const SOURCE_DATABASE = 2;
	const SOURCE_REMOTE = 3;
	
	const PUT_REQUEST_STATUS_FAIL = 1;
	const PUT_REQUEST_STATUS_SUCCESS = 2; 
	
	public static $lastCurlInfo;
	public $data;
	public $request;
	
	public static function initialise(){
		$t = Config::get("API_CACHE_TABLE");
		static::$config['TABLE_NAME'] = $t;
		$url = Config::get('API_REMOTE_URL');
		if(empty($url))throw new Error("API remote URL does not have a value");
		static::$config['API_REMOTE_URL'] = $url;
		
		$sql = "SELECT * FROM $t WHERE request=:request";
		static::$config['SELECT_ROW_SQL'] = $sql;
	}
	
	public static function init($dbh, $source = null){
		self::$dbh = $dbh;
		static::$source = $source;
		static::initialise();
	}
	
	public static function createRequest($req){
		return static::createInstance(self::$dbh, array('request'=>$req));
	}
	
	private static function collection2dataArray($collection, $fields2unset = null){
		$ar = array();
		if($fields2unset && is_string($fields2unset))$fields2unset = explode(',', $fields2unset);
		foreach($collection as $obj){
			$row = $obj->rowdata;
			if($fields2unset){
				foreach($fields2unset as $f)unset($row[$f]);
			}
			array_push($ar, $row);
		}
		return $ar;
	}
	
	protected static function translateGetRequest(&$req, &$params){
		$request = explode('/', $req);
		switch($request[0]){
			case 'locations-nearby':
				$deviceID = $request[1];
				$device = ClientDevice::createInstance(self::$dbh, array('device_id'=>$deviceID));
				$coords = $device->getLocationCoords(Config::get('USE_NETWORK_LOCATION'));
				if($coords){
					$req = 'locations';
					$params['lat'] = $coords['latitude'];
					$params['lon'] = $coords['longitude'];
				} else {
					throw new Exception("Requesting nearby locations but cannot find location for device $deviceID");
				}
				break;
				
				
			case 'device':
			case 'devices':
			case 'about':
				static::$source = self::SOURCE_DATABASE;
				break;
				
			default:
				break;
		}
	}
	
	public static function processGetRequest($req, $params = null){
		
		if(empty($req))throw new Exception("API request cannot be empty");
		static::translateGetRequest($req, $params);		
		$request = explode('/', $req); //split in to array for processing
		$data = null;
		
		//now we handle the specific requests
		switch(static::$source){
			case self::SOURCE_DATABASE:
				switch($request[0]){
					case 'batch':
						//we deal with the 'meta' case of this being a batch of requests
						if(!isset($params['requests']))throw new Exception("No requests in batch");
						$requests = explode(',', $params['requests']);
						unset($params['requests']);
						$data = array();
						foreach($requests as $req){
							$data[$req] = static::processGetRequest($req, $params);
						}
						break;
					
					case 'about':
						$data = array();
						$data['timezone_offset'] = static::tzoffset();
						$data['source'] = Config::get('API_SOURCE');
						$data['api_remote_url'] = Config::get('API_REMOTE_URL');
						$data['use_network_location'] = Config::get('USE_NETWORK_LOCATION');
						break;
					
					case 'locations';
						$locations = Location::createCollection(self::$dbh);
						$data = static::collection2dataArray($locations);
						break;
						
					case 'sources':
						$sources = Sources::createCollection(self::$dbh);
						$data = static::collection2dataArray($sources);
						break;
						
					case 'forecast':
						if(!isset($request[1]))throw new Exception("No location provided");
						$locationID = $request[1];
						if(empty($locationID))throw new Exception("No location id present");
						$location = Location::createInstanceFromID(self::$dbh, $locationID);
						if(empty($location))throw new Exception("No location found for $locationID");
						$lastFeedRun = FeedRun::getLastRun(self::$dbh);
						if(empty($lastFeedRun->id))throw new Exception("No feed run found");
						$weighting = Config::get('FORECAST_WEIGHTING'); 
						$restrict2sources = null; //possible parameter
						$forecast = Forecast::getSynthesis(self::$dbh, $lastFeedRun, $location, $weighting, $restrict2sources);
						
						$prevFeedRun = null;
						try{
							$secsOld = $lastFeedRun->rowdata['secs'] + 2*24*3600;
							$prevFeedRun = FeedRun::getLastRun(self::$dbh, $secsOld);
						} catch (Exception $e){
							
						}
						if($prevFeedRun && $prevFeedRun->id){
							$prevForecast = Forecast::getSynthesis(self::$dbh, $prevFeedRun, $location, $weighting, $restrict2sources);
							$forecast = Forecast::combineSyntheses($forecast, $prevForecast);
							
							//ugly hack here as the most recent forecast current day is sometimes not complete depending on when the download was done (e.g after first tide extreme)
							//as a result we use the previous forecast day
							//TODO: some logic that preserves the incomplete data on the current day rather than overwriting it
							$key = date("Y-m-d")." ".$forecast['timezone_offset'];
							if(isset($prevForecast['days'][$key])){
								$forecast['days'][$key] = $prevForecast['days'][$key];
							}
						}
						
						$data = $forecast;
						if(isset($request[2]) && isset($data[$request[2]])){ //allow for array key referencing in URL
							$data = $data[$request[2]];
						}
						
						break;
						
					case 'device':
						if(!isset($request[1]))throw new Exception("No device ID provided");
						$device = ClientDevice::createInstance(self::$dbh, array('device_id'=>$request[1]));
						$data = $device->rowdata;
						Utils::convertToUTC($data, 'created,last_updated,location_last_updated');
						break;
						
					case 'devices':
						if(!isset($request[1]))throw new Exception("No device network provided");
						$params = array('device_network'=>$request[1], 'device_id'=>0);
						$devices = ClientDevice::createCollection(self::$dbh, $params);
						$data = static::collection2dataArray($devices);
						break;
						
					case 'device-location':
						if(!isset($request[1]))throw new Exception("No device ID provided");
						$device = ClientDevice::createInstance(self::$dbh, array('device_id'=>$request[1]));
						$data = $device->getLocationCoords(Config::get('USE_NETWORK_LOCATION'));
						if(!$data){
							throw new Exception("Cannot find location for device $deviceID");
						} else {
							Utils::convertToUTC($data, 'created,last_updated,location_last_updated');
						}
						break;
						
					case 'digests':
						if(!$params || !isset($params['status']))throw new Exception("No status parameter set");
						$digests = Digest::createCollection(self::$dbh, $params);
						$data = static::collection2dataArray($digests, 'digest');
						break;
						
					case 'feeds':
						$feeds = Feed::createCollection(self::$dbh, $params);
						$data = static::collection2dataArray($feeds);
						break;
						
					default:
						throw new Exception("Unknown GET request $req");
						break;
				}
				break;
				
			case self::SOURCE_CACHE:
				$apiRequest = static::createInstance(self::$dbh, array('request'=>$req));
				try{
					if(empty($apiRequest->id))throw new Exception("Request $req is not present in cache");
					$apiRequest->setRowData(array('last_requested'=>self::now()));
					$apiRequest->write();
					$data = json_decode($apiRequest->data, true);
					break;
				} catch (Exception $e){
					//could download here ..Log?
					throw $e;
				}
				
			case self::SOURCE_REMOTE:
				$apiRequest = static::createRequest($req);
				try{
					$apiRequest->get($params); //call a remote version of the API to download the request
					$data = json_decode($apiRequest->data, true);
					break;
				} catch (Exception $e){
					throw $e;
				}
				break;
				
			default:
				throw new Exception("Request source $source is not recognised");
		}
		
		//we post process the data depending on request and querystring
		if(!empty($params)){
			switch($request[0]){
				case 'locations':
					if(!empty($params['lon']) && !empty($params['lat'])){
						$lat1 = $params['lat'];
						$lon1 = $params['lon'];
						$minD = isset($params['distance']) ? $params['distance'] : -1;
						$data2return = array();
						foreach($data as $location){
							$lat2 = $location['latitude'];
							$lon2 = $location['longitude'];
							$location['distance'] = round(Utils::distance($lat1, $lon1, $lat2, $lon2),2);
							$include = $minD < 0 ? true : ($location['distance'] <= $minD);
							if($include)array_push($data2return, $location);
						}
						$data = $data2return;
						usort($data, function($a, $b){ 
								if($a['distance'] == $b['distance'])return 0; 
								return $a['distance'] > $b['distance'] ? 1 : -1; 
							}
						);
					}
					break;
			}
		}
		
		return $data;
	}
	
	
	public static function processPutRequest($req, $params){
		if(empty($req))throw new Exception("API request cannot be empty");
		if(empty($params))throw new Exception("No parameters to PUT");
		$request = explode('/', $req); //split in to array for processing

		$data = null;
		switch(static::$source){
			case self::SOURCE_DATABASE:
			case self::SOURCE_CACHE:
				switch($request[0]){
					case 'batch':
						break;
					
					case 'device':
						$params['device_id'] = $request[1];
						if(empty($params['is_location_set'])){
							$params['latitude'] = null;
							$params['longitude'] = null;
							$params['location_accuracy'] = null;
						}
						if(!empty($params['device_network'])){ //sometimes these are sent with quotation marks around
							$params['device_network'] = str_ireplace('"','', $params['device_network']);
						}
						unset($params['is_location_set']);
						unset($params['last_updated']);
						$clientDevice = ClientDevice::createInstance(self::$dbh, $params);
						$clientDevice->write();
						$data = $clientDevice->getWithLocationData(Config::get('USE_NETWORK_LOCATION'));
						Utils::convertToUTC($data, 'created,last_updated,location_last_updated');
						break;

					case 'sources':
					case 'source':
						if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot PUT $req to cache");
						if(empty($request[1]))throw new Exception("No ID passed");
						$r = Sources::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$r->setRowData($params);
						$r->write();
						$data = array('id'=>$r->id);
						break;
						
					case 'locations':
					case 'location':
						if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot PUT $req to cache");
						if(empty($request[1]))throw new Exception("No ID passed");
						$r = Location::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$r->setRowData($params);
						$r->write();
						$data = array('id'=>$r->id);
						break;
						
					case 'feeds':
					case 'feed':
						if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot PUT $req to cache");
						if(empty($request[1]))throw new Exception("No ID passed");
						$r = Feed::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$r->setRowData($params);
						$r->write();
						$data = array('id'=>$r->id);
						break;
						
					default:
						throw new Exception("Unrecognised PUT request ".$request[0]);
						break;
				}
				break;
				
			case self::SOURCE_REMOTE:
				throw new Exception("PUT to remote source not yet implemented");
				break;
				
		}
		
		return $data;
	}
	
	public static function processPostRequest($req, $params){
		if(empty($req))throw new Exception("API request cannot be empty");
		if(empty($params))throw new Exception("No parameters to POST");
		$request = explode('/', $req); //split in to array for processing

		$data2return = null;
		switch(static::$source){
			case self::SOURCE_DATABASE:
			case self::SOURCE_CACHE: 
				switch($request[0]){
					case 'digests':
					case 'digest':
						unset($params['id']);
						unset($params['created']);
						$params['source'] = isset($_SERVER) && isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
						$params['status'] = Digest::STATUS_RECEIVED;
						$digest = Digest::addDigest(self::$dbh, $params);
						$data2return = array('id'=>$digest->id);
						break;
						
					case 'sources':
					case 'source':
						$r = Sources::createInstance(self::$dbh, null, null);
						$r->setRowData($params, true);
						$id = $r->write();
						$data2return = array('id'=>$id);
						break;
						
					case 'locations':
					case 'location':
						$r = Location::createInstance(self::$dbh, null, null);
						$r->setRowData($params, true);
						$id = $r->write();
						
						//we write default feeds for this location based on sources
						$sources = Sources::createCollection(self::$dbh);
						foreach($sources as $s){
							$f = Feed::createInstance(self::$dbh, null, null);
							$vals = array();
							$vals['source_id'] = $s->rowdata['id'];
							$vals['location_id'] = $id;
							$vals['querystring'] = $s->rowdata['default_querystring'];
							$vals['endpoint'] = $s->rowdata['default_endpoint'];
							$f->setRowData($vals);
							$f->write();
						}
						
						$data2return = array('id'=>$id);
						break;
						
					default:
						throw new Exception("Unrecognised POST request ".$request[0]);
						break;
				}
				break;
				
			case self::SOURCE_REMOTE:
				throw new Exception("POST to remote source not yet implemented");
				break;
		}
		
		return $data2return;
	}
	
	public static function processDeleteRequest($req, $params){
		if(empty($req))throw new Exception("API request cannot be empty");
		if(empty($params))throw new Exception("No parameters to PUT");
		$request = explode('/', $req); //split in to array for processing

		$data = null;
		switch(static::$source){
			case self::SOURCE_DATABASE:
			case self::SOURCE_CACHE:
				switch($request[0]){
					case 'batch':
						break;
					
					case 'sources':
					case 'source':
						if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot DELETE $req from cache");
						if(empty($request[1]))throw new Exception("No ID passed");
						$r = Sources::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$data = array('id'=>$r->delete());
						break;
						
					case 'locations':
					case 'location':
						if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot DELETE $req from cache");
						$r = Location::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$data = array('id'=>$r->delete());
						break;
						
					case 'feeds':
					case 'feed':
						if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot DELETE $req from cache");
						$r = Feed::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$data = array('id'=>$r->delete());
						break;
						
					default:
						throw new Exception("Unrecognised DELETE request".$request[0]);
						break;
				}
		}
	}
	
	
	public static function buildRequest($req){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $req); 
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, Config::get('CURLOPT_CONNECTTIMEOUT',30));
		curl_setopt($ch, CURLOPT_TIMEOUT, Config::get('CURLOPT_TIMEOUT',30));
		curl_setopt($ch, CURLOPT_ENCODING, ''); //accept all encodings
		return $ch;        
	}
	
	public static function executeRequest($ch){
		$data = curl_exec($ch); 
	    $error = curl_error($ch);
	    $errno = curl_errno($ch);
	    $info = curl_getinfo($ch);
	    curl_close($ch);
	    
	    static::$lastCurlInfo = $info;
        	
	    if($errno != 0){
	    	throw new Exception("cURL error", $errno);
	    } else if($info['http_code'] >= 400){
	    	throw new Exception("HTTP Error ".$info['http_code'].' '.$data, $info['http_code']);
		} else {
			return $data;
        }
	}
	
	public static function getRequestInfo($key = null){
		return $key ? static::$lastCurlInfo[$key] : static::$lastCurlInfo; 
	}
	
	public static function save2cache($requestData){
		if(is_string($requestData)){
			$requestData = json_decode($requestData, true);
			if(json_last_error())throw new Exception(json_last_error());
		}
		foreach($requestData as $request=>$data){
			$apiRequest = static::createInstance(self::$dbh, array('request'=>$request));
			$vals['data'] = json_encode($data);
			if($apiRequest->id){
				$vals['last_updated'] = self::now();
			}
			$apiRequest->setRowData($vals);
			$apiRequest->write(); //which writes the data to cache
		}
		return $requestData;
	}
	
	public static function output($data, $headers = null){
		header('Content-Type: application/json');
		header('X-Server-Time: '.time());
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
		header("Pragma: no-cache"); // HTTP 1.0.
		header("Expires: 0"); // Proxies.
		echo is_array($data) ? json_encode($data) : $data;
	}
	
	public static function exception($e, $httpCode = 404, $httpMessage = "Not Found"){
		header("HTTP/1.0 $httpCode $httpMessage", true, $httpCode);
		$ex = array();
		$ex['message'] = $e->getMessage();
		$ex['error_code'] = $e->getCode();
		$ex['http_code'] = $httpCode;
		static::output($ex);
	}
	
	
	function __construct($rowData, $readFromDB = true){
		parent::__construct($rowData, $readFromDB);
		
		$this->assignR2V($this->data, 'data');
		$this->assignR2V($this->request, 'request');
	}
	
	
	public function get($params = null, $baseURL = null){
		return $this->request($params, 'GET', $baseURL);
	}
	
	public function post($params, $baseURL = null){
		if(empty($params))throw new Exception("No data to POST");
		return $this->request($params, 'POST', $baseURL);
	}
	
	public function put($params, $baseURL = null){
		if(empty($params))throw new Exception("No data to PUT");
		return $this->request($params, 'PUT', $baseURL);
	}
	
	
	
	public function request($params = null, $method = 'GET', $baseURL = null){
		//retrieve data
		if(!$baseURL)$baseURL = static::$config['API_REMOTE_URL'];
		if($baseURL && strrpos($baseURL,'/') == strlen($baseURL) - 1){
			$baseURL = substr($baseURL, 0, strlen($baseURL) - 1);
		}
		$url = $baseURL."/".$this->request;
		
		if($params && $method == 'GET'){
			$qs = '';
			foreach($params as $k=>$v){
				$qs.= ($qs ? '&' : '')."$k=".urlencode($v);
			}
			$url = $url.'?'.$qs;
		}
		
		try{
			$ch = self::buildRequest($url);
			switch($method){
				case 'PUT':
					if(empty($params))throw new Exception("No data to PUT");
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
					break;
					
				case 'POST':
					if(empty($params))throw new Exception("No data to POST");
					curl_setopt($ch, CURLOPT_POST, 1);
        			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
					break;
			}
			
			$data = self::executeRequest($ch);
			
			$this->setData($data);
	    	return $this->data;
		} catch (Exception $e){
			throw $e;
		}
	}
	
	public function setData($data){
		$this->data = $data;
		$this->setRowData(array('data'=>$data));
	}
}
?>