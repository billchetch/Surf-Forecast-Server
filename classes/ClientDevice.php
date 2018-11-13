<?php
class ClientDevice extends DBObject{
	static public $config = array();
	
	public static function initialise(){
		$t = Config::get("CLIENT_DEVICES_TABLE");
		static::$config['TABLE_NAME'] = $t;
		$sql = "SELECT * FROM $t l WHERE device_network=:device_network AND (location_accuracy IS NOT NULL || device_id=:device_id) ORDER BY IF(location_accuracy IS NULL, 1, 0), IF(now()-last_updated<60*30,0,1), location_accuracy, last_updated DESC";
		static::$config['SELECT_ROWS_SQL'] = $sql;
		static::$config['SELECT_ROW_SQL'] = "SELECT * FROM $t WHERE device_id=:device_id ORDER BY last_updated DESC";
	}
	
	public static function getNetworkLocationCoords($network){
		$params = array();
		$params['device_id'] = '0';
		$params['device_network'] = $network;
		$device = static::createInstance(self::$dbh, $params);
		return $device->getLocationCoords(true);
	}
	
	public function getWithLocationData($useNetwork){
		$data = $this->rowdata;
		$coords = $this->getLocationCoords($useNetwork);
		if($coords){
			$data['latitude'] = $coords['latitude'];
			$data['longitude'] = $coords['longitude'];
			$data['location_accuracy'] = $coords['location_accuracy'];
			$data['location_device_id'] = $coords['device_id'];
			$data['location_last_updated'] = $coords['last_updated'];
		} else {
			$data['location_device_id'] = null;
			$data['location_last_updated'] = null;
		}
		return $data;
	}
	
	public function getLocationCoords($useNetwork = true){
		if($useNetwork){
			if(empty($this->rowdata['device_network']))throw new Exception("No device network set for this device");
			$deviceNetwork = $this->rowdata['device_network'];
			$deviceID = $this->rowdata['device_id'];
			$networks = array($deviceNetwork);
			$ar = Config::getAsArray('FRIEND_NETWORKS');
			if($ar){
				foreach($ar as $nw){
					array_push($networks, $nw);
				}
			}
			
			foreach($networks as $network){
				$params = array('device_id'=>$deviceID, 'device_network'=>$network);
				$devices = static::createCollection(self::$dbh, $params);
				foreach($devices as $device){
					if(!empty($device->rowdata['latitude']) && !empty($device->rowdata['longitude'])){
						return $device->rowdata;	
					}
				}
			}
			return null;
		} else { //don't use network so just return this device data
			if($this->rowdata['location_accuracy'] !== null){
				return $this->rowdata;
			} else {
				return null;
			}
		}
	}
	
	public function write(){
		if(!empty($this->id)){
			if(empty($this->rowdata['last_update']))$this->rowdata['last_updated'] = self::now();
			$this->rowdata['update_count']++;
		}
		parent::write();
	}
}
