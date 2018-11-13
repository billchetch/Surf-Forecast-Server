<?php
class HuaweiE5673Router extends Router{
	function parseRequest($data){
		return $data;
	}
	
	function request($req, $params = null, $method='GET'){
		return parent::request('api/'.$req, $params, $method);
	}
	
	function login(){
		parent::login();
	}
	
	function logout(){
		parent::logout();
	}
	
	function reboot(){
		
	}
	
	function getDeviceInfo(){
		$data = $this->request('monitoring/status');
		return $data;
	}
}
?>