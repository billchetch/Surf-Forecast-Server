<?php
class TPLinkM7350Router extends Router{
	
	function addCurlOptions(&$ch, $req, $params = null, $method = null){
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_COOKIE, 'check_cookie=check_cookie');
	}
	
	function addRequestHeaders($req, $params, $method){
		$this->addRequestHeader('Referer',$this->protocol.'://'.$this->ip.'/login.html');
		$this->addRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		$this->addRequestHeader('Accept','application/json, text/javascript, */*; q=0.01');
		$this->addRequestHeader('Accept-Language', 'en-US,en;q=0.9,id;q=0.8');
		$this->addRequestHeader('X-Requested-With', 'XMLHttpRequest');
		$this->addRequestHeader('Origin', $this->protocol.'://'.$this->ip);
		$this->addRequestHeader('Accept-Encoding', 'gzip, deflate');
		$this->addRequestHeader('Connection','keep-alive');
	}
	
	function request($req, $params = null, $method='POST'){
		if($this->loggedIn && $this->token){
			if(empty($params))$params = array();
			$params['token'] = $this->token;
		}
		if($params && is_array($params)){
			$params = json_encode($params, JSON_NUMERIC_CHECK);
		}
		
		return parent::request($req, $params, $method);
	}
	
	function parseRequest($data, $req, $params, $method){
		if($req == 'i18n/str_en.properties'){
			return $data;
		} else {
			return parent::parseRequest($data, $req, $params, $method);
		}	
	}
	
	function getParams($module, $action){
		$params = array();
		$params['module'] = $module;
		$params['action'] = $action;
		return $params;
	}
	
	function login(){
		$params = $this->getParams("authenticator", 0);
		$data = $this->request('cgi-bin/auth_cgi', $params);
		if(empty($data['nonce']))throw new Exception("No NONCE value returned");
		$nonce = $data['nonce'];
		
		$pw = Config::get('INTERNET_ROUTER_PASSWORD');
		$s = $pw.':'.$nonce;
		$digest = md5($s, false);
		$params = $this->getParams("authenticator", 1);
		$params['digest'] = $digest; 
		$data = $this->request('cgi-bin/auth_cgi', $params);
		if($data && isset($data['result']) && $data['result'] == 0){
			if(!isset($data['token']))throw new Exception("No token found");
			$this->token = $data['token'];
			parent::login();
			return $data;
		} else {
			throw new Exception("Failed to login");
		}
	}
	
	function logout(){
		$params = $this->getParams("authenticator", 3);
		$data = $this->request('cgi-bin/auth_cgi', $params);
		parent::logout();
		return $data;
	}
	
	
	function reboot(){
		$params = $this->getParams("reboot", 0);
		return $this->request('cgi-bin/web_cgi', $params);
	}
	
	function getDeviceInfo(){
		$params = $this->getParams("status", 0);
		return $this->request('cgi-bin/web_cgi', $params);
	}
}
?>