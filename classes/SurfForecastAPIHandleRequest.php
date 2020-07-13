<?php
use chetch\api\APIException as APIException;
use chetch\Config as Config;
use chetch\Utils as Utils;

class SurfForecastAPIHandleRequest extends chetch\api\APIHandleRequest{
	const PUT_REQUEST_STATUS_FAIL = 1;
	const PUT_REQUEST_STATUS_SUCCESS = 2; 
	
	protected function translateGetRequest(&$req, &$params){
		$requestParts = explode('/', $req);
		switch($requestParts[0]){
			case 'locations-nearby':
				$deviceID = isset($requestParts[1]) ? $requestParts[1] : null; //not used but might be in the future ...
				GPS::init(self::$dbh);
				$coords = GPS::getLatest();
				if($coords){
					$req = 'locations';
					$params['lat'] = $coords->latitude;
					$params['lon'] = $coords->longitude;
				} else {
					throw new Exception("Requesting nearby locations but cannot find location for device $deviceID");
				}
				break;
				
				
			case 'device':
			case 'devices':
			case 'about':
			case 'location-info':
				$this->source = self::SOURCE_DATABASE;
				break;
				
			default:
				break;
		}
	}
	
	protected function processGetRequest($request, $params){
		
		$this->translateGetRequest($request, $params);	
		
		$data = array();
		$requestParts = explode('/', $request);
		
		//now we handle the specific requests
		switch($this->source){
			case self::SOURCE_DATABASE:
				switch($requestParts[0]){
					case 'about':
						$data = self::about();
						$data['source'] = $this->source;
						$data['api_remote_url'] = \chetch\Config::get('API_REMOTE_URL');
						$data['use_network_location'] = \chetch\Config::get('USE_NETWORK_LOCATION');
						/*GPS::init(self::$dbh);
						$coords = GPS::getLatest();
						if($coords){
							$data['latitude'] = $coords->latitude;
							$data['longitude'] = $coords->longitude;
						} else {
							$data['latitude'] = null;
							$data['longitude'] = null;
						}*/
						break;
						
					case 'location-info':
						if(!isset($params['date']))throw new Exception("No date passed in query");
						if(!isset($params['lat']))throw new Exception("Latitude not passed in query");
						if(!isset($params['lon']))throw new Exception("Longitude not passed in query");
						$dt = $params['date'];
						$lat = $params['lat'];
						$lon = $params['lon'];
						
						//pass back data for convenience
						$data['latitude'] = $lat;
						$data['longitude'] = $lon; 
						
						//we extract the date part only of the request
						$dto = explode(' ', $dt)[0];
						$tzo = self::tzoffset();
						$t = strtotime($dto);
						$suninfo = date_sun_info($t, $lat, $lon);
						$dt = new DateTime(date('Y-m-d H:i:s', $suninfo['civil_twilight_begin']));
						$data['first_light'] = $dt->format('Y-m-d H:i:s').' '.$tzo;
						$dt = new DateTime(date('Y-m-d H:i:s', $suninfo['civil_twilight_end']));
						$data['last_light'] = $dt->format('Y-m-d H:i:s').' '.$tzo;
						break;
						
					case 'locations';
						$data = Location::createCollectionAsRows();
						break;
						
					case 'sources':
						$data = Sources::createCollectionAsRows();
						break;
						
					case 'forecast':
					case 'forecast-daylight':
						if(!isset($requestParts[1]))throw new Exception("No location provided");
						$locationID = $requestParts[1];
						if(empty($locationID))throw new Exception("No location id present");
						$location = Location::createInstanceFromID($locationID);
						if(empty($location))throw new Exception("No location found for $locationID");
						$lastFeedRun = FeedRun::getLastRun();
						if(empty($lastFeedRun->getID()))throw new Exception("No feed run found");
						
						$weighting = Config::get('FORECAST_WEIGHTING'); 
						$forecast = Forecast::getSynthesis($lastFeedRun, $location, $weighting);
						try{
							$secsOld = $lastFeedRun->get('secs') + 2*24*3600;
							$prevFeedRun = FeedRun::getLastRun($secsOld);
							if($prevFeedRun && $prevFeedRun->id){
								$prevForecast = Forecast::getSynthesis($prevFeedRun, $location, $weighting);
								$forecast = Forecast::combineSyntheses($forecast, $prevForecast);
								
								//ugly hack here as the most recent forecast current day is sometimes not complete depending on when the download was done (e.g after first tide extreme)
								//as a result we use the previous forecast day
								//TODO: some logic that preserves the incomplete data on the current day rather than overwriting it
								$key = date("Y-m-d")." ".$forecast['timezone_offset'];
								if(isset($prevForecast['days'][$key])){
									$forecast['days'][$key] = $prevForecast['days'][$key];
								}
							}
						} catch (Exception $e){
							
						}
						
						//Allow for specifying only hours within daylight
						if($requestParts[0] == 'forecast-daylight'){
							$tzo = $forecast['timezone_offset'];
							foreach($forecast['hours'] as $key=>$hour){
								$dkey = explode(' ', $key);
								if(count($dkey) != 3)conntinue;
								$dkey = $dkey[0].' '.$dkey[2];
								if(!isset($forecast['days'][$dkey]))continue;
								
								$day = $forecast['days'][$dkey];
								$fl = trim(str_replace($tzo, '', $day['first_light']));
								$ll = trim(str_replace($tzo, '', $day['last_light']));
								$flh = (int)date("H", strtotime($fl));
								$llh = (int)date("H", strtotime($ll));
								
								$hkey = trim(str_replace($tzo, '', $key));
								$h = (int)date("H", strtotime($hkey));
								if($h < $flh || $h > $llh + 1){
									unset($forecast['hours'][$key]);
								}
							}
						}
						$data = $forecast;
						
						//allow for array key referencing in URL
						if(isset($request[2]) && isset($data[$request[2]])){ 
							$data = $data[$request[2]];
						}
						break;
						
					case 'device':
						if(!isset($requestParts[1]))throw new Exception("No device ID provided");
						$device = ClientDevice::createInstance(array('device_id'=>$request[1]));
						$data = $device->getRowData();
						Utils::convertToUTC($data, 'created,last_updated,location_last_updated');
						break;
						
					case 'devices':
						if(!isset($request[1]))throw new Exception("No device network provided");
						$params = array('device_network'=>$requestParts[1], 'device_id'=>0);
						$devices = ClientDevice::createCollection($params);
						$data = static::collection2dataArray($devices);
						break;
						
					case 'device-location':
						if(!isset($requestParts[1]))throw new Exception("No device ID provided");
						$device = ClientDevice::createInstance(array('device_id'=>$requestParts[1]));
						$data = $device->getLocationCoords(Config::get('USE_NETWORK_LOCATION'));
						if(!$data){
							throw new Exception("Cannot find location for device $deviceID");
						} else {
							Utils::convertToUTC($data, 'created,last_updated,location_last_updated');
						}
						break;
						
					case 'digests':
						if(!$params || !isset($params['status']))throw new Exception("No status parameter set");
						$data = Digest::createCollectionAsRows($params);
						break;
						
					case 'feeds':
						$data = Feed::createCollectionAsRows($params);
						break;
						
					default:
						throw new Exception("Unknown GET request $req");
						break;
				}
				break;
				
			case self::SOURCE_CACHE:
				//this will attempt to read a request result direct from cache table
				try{
					if(empty($this->id))throw new Exception("Request $req is not present in cache");
					$this->setRowData(array('last_requested'=>self::now()));
					//$this->write();
					//$data = json_decode($this->data, true);
					break;
				} catch (Exception $e){
					//could download here ..Log?
					throw $e;
				}
				
			case self::SOURCE_REMOTE:
				try{
					//$apiRequest->get($params); //call a remote version of the API to download the request
					//$data = json_decode($apiRequest->data, true);
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
			switch($requestParts[0]){
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
					
					if(!empty($params['max_locations'])){
						$data2return = array();
						for($i = 0; $i < $params['max_locations']; $i++){
							array_push($data2return, $data[$i]);
						}
						$data = $data2return;
					}
					break;
			}
		}
		
		return $data;
	}
	
	
	public function processPutRequest($request, $params, $payload){
		if(empty($request))throw new Exception("API request cannot be empty");
		if(empty($params))throw new Exception("No parameters to PUT");
		$requestParts = explode('/', $request); //split in to array for processing

		$data = null;
		switch($this->source){
			case self::SOURCE_DATABASE:
			case self::SOURCE_CACHE:
				switch($requestParts[0]){
					case 'batch':
						break;
					
					case 'device':
						/*$params['device_id'] = $request[1];
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
						Utils::convertToUTC($data, 'created,last_updated,location_last_updated');*/
						break;

					case 'sources':
					case 'source':
						/*if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot PUT $req to cache");
						if(empty($request[1]))throw new Exception("No ID passed");
						$r = Sources::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$r->setRowData($params);
						$r->write();
						$data = array('id'=>$r->id);*/
						break;
						
					case 'locations':
					case 'location':
						/*if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot PUT $req to cache");
						if(empty($request[1]))throw new Exception("No ID passed");
						$r = Location::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$r->setRowData($params);
						$r->write();
						$data = array('id'=>$r->id);*/
						break;
						
					case 'feeds':
					case 'feed':
						/*if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot PUT $req to cache");
						if(empty($request[1]))throw new Exception("No ID passed");
						$r = Feed::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$r->setRowData($params);
						$r->write();
						$data = array('id'=>$r->id);*/
						break;
						
					default:
						throw new Exception("Unrecognised PUT request ".$request[0]);
						break;
				}
				break;
				
			case $this->SOURCE_REMOTE:
				throw new Exception("PUT to remote source not yet implemented");
				break;
				
		}
		
		return $data;
	}
	
	public function processPostRequest($request, $params, $payload){
		if(empty($request))throw new Exception("API request cannot be empty");
		if(empty($params))throw new Exception("No parameters to POST");
		$requestParts = explode('/', $request); //split in to array for processing

		$data2return = null;
		switch($this->source){
			case self::SOURCE_DATABASE:
			case self::SOURCE_CACHE: 
				switch($requestParts[0]){
					case 'digests':
					case 'digest':
						/*unset($params['id']);
						unset($params['created']);
						$params['source'] = isset($_SERVER) && isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
						$params['status'] = Digest::STATUS_RECEIVED;
						$digest = Digest::addDigest(self::$dbh, $params);
						$data2return = array('id'=>$digest->id);*/
						break;
						
					case 'sources':
					case 'source':
						/*$r = Sources::createInstance(self::$dbh, null, null);
						$r->setRowData($params, true);
						$id = $r->write();
						$data2return = array('id'=>$id);*/
						break;
						
					case 'locations':
					case 'location':
						/*$r = Location::createInstance(self::$dbh, null, null);
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
							$vals['payload'] = $s->rowdata['default_payload'];
							$vals['endpoint'] = $s->rowdata['default_endpoint'];
							$f->setRowData($vals);
							$f->write();
						}
						
						$data2return = array('id'=>$id);*/
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
	
	public function processDeleteRequest($request, $params){
		if(empty($request))throw new Exception("API request cannot be empty");
		if(empty($params))throw new Exception("No parameters to PUT");
		$requestParts = explode('/', $request); //split in to array for processing

		$data = null;
		switch($this->source){
			case self::SOURCE_DATABASE:
			case self::SOURCE_CACHE:
				switch($requestParts[0]){
					case 'sources':
					case 'source':
						/*if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot DELETE $req from cache");
						if(empty($request[1]))throw new Exception("No ID passed");
						$r = Sources::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$data = array('id'=>$r->delete());*/
						break;
						
					case 'locations':
					case 'location':
						/*if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot DELETE $req from cache");
						$r = Location::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$data = array('id'=>$r->delete());*/
						break;
						
					case 'feeds':
					case 'feed':
						/*if(static::$source == self::SOURCE_CACHE)throw new Exception("Cannot DELETE $req from cache");
						$r = Feed::createInstanceFromID(self::$dbh, $request[1], null);
						$r->setID($request[1]);
						$data = array('id'=>$r->delete());*/
						break;
						
					default:
						throw new Exception("Unrecognised DELETE request".$requestParts[0]);
						break;
				}
			case self::SOURCE_REMOTE:
				break;
		}
		return $data;
	} 
}
?>