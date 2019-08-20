<?php
class DLinkDWR932CRouter extends Router{
	function request($req, $params = null, $method='GET'){
		if($this->loggedIn && $this->token){
			if(empty($params))$params = array();
			$params['token'] = $this->token;
		}
		return parent::request('cgi-bin/'.$req, $params, 'POST');
	}
	
	function login(){
		$pwd = Config::get('INTERNET_ROUTER_PASSWORD');
		$params = array(
			'type' => 'login',
			'pwd' => md5($pwd),
			'timeout'=>'300',
			'user'=> Config::get('INTERNET_ROUTER_USERNAME')
		);
		
		$data = $this->request('qcmap_auth', $params);
		
		if($data && isset($data['result']) && $data['result'] == 0){
			if(!isset($data['token']))throw new Exception("No token found");
			$this->token = $data['token'];
			parent::login();
		} else {
			throw new Exception("Failed to login");
		}
	}
	
	function logout(){
		$params = array(
			'type' => 'close',
			'timeout'=>'300',
		);
		
		$data = $this->request('qcmap_auth', $params);
		parent::logout();
	}
	
	function getParams($page){
		$params = array();
		$params['Page'] = $page;
		$params['mask'] = 0;
		return $params;
	}
	
	function reboot(){
		$params = $this->getParams('system_reboot');
		return $this->request('qcmap_web_cgi', $params);
	}
	
	function getDeviceInfo(){
		$data = array();
		$pages = array('get_charge_capacity', 'GetWanConnectedTime', 'GetSystemAbout');
		foreach($pages as $page){
			$params = $this->getParams($page);
			$data = array_merge($data, $this->request('qcmap_web_cgi', $params, 'POST'));	
		}
		
		$data['battery_level'] = $data['capacity'].'%';
		unset($data['capacity']);
		unset($data['Page']);
		unset($data['result']);
		return $data;
	}
	
	
	function getWifiInfo(){
		$params = $this->getParams('GetWiFiClients');
		$data = $this->request('qcmap_web_cgi', $params, 'POST');
		
		unset($data['Page']);
		unset($data['result']);
		return $data;
	}
	
	function getWanInfo(){
		$data = array();
		$pages = array('GetWanStatus', 'GetWanConnectedTime', 'GetWLANConfig', 'GetWWANSTATS');
		foreach($pages as $page){
			$params = $this->getParams($page);
			$data = array_merge($data, $this->request('qcmap_web_cgi', $params, 'POST'));	
		}
		
		unset($data['Page']);
		unset($data['result']);
		return $data;
	}
}
?>