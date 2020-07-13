<?php
use \chetch\Config as Config;

class ClientDevice extends chetch\db\DBObject{
	
	public static function initialise(){
		$t = Config::get("CLIENT_DEVICES_TABLE", 'client_devices');
		static::setConfig('TABLE_NAME', $t);
		$sql = "SELECT * FROM $t";
		static::setConfig('SELECT_DEFAULT_FILTER', "device_network=:device_network AND (location_accuracy IS NOT NULL || device_id=:device_id)");
		static::setConfig('SELECT_DEFAULT_ORDER', "IF(location_accuracy IS NULL, 1, 0), IF(now()-last_updated<60*30,0,1), location_accuracy, last_updated DESC");
		static::setConfig('SELECT_SQL', $sql);
		static::setConfig('SELECT_ROW_SQL', "SELECT * FROM $t WHERE device_id=:device_id ORDER BY last_updated DESC");
	}
	
	public static function getNetworkLocationCoords($network){
		$params = array();
		$params['device_id'] = '0';
		$params['device_network'] = $network;
		$device = static::createInstance($params);
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
			if(empty($this->rowdata['device_network']))throw new Exception("No device network set for this device ".$this->rowdata['device_id']);
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
				$devices = static::createCollection($params);
				foreach($devices as $device){
					if(!empty($device->get('latitude')) && !empty($device->get('longitude'))){
						return $device->getRowData();	
					}
				}
			}
			return null;
		} else { //don't use network so just return this device data
			if($this->get('location_accuracy') !== null){
				return $this->getRowData();
			} else {
				return null;
			}
		}
	}
	
	public function write($readAgain = false){
		if(!empty($this->id)){
			if(empty($this->get('last_update')))$this->set('last_updated', self::now());
			$this->set('update_count', $this->get('update_count') + 1);
		}
		parent::write($readAgain);
	}
}
