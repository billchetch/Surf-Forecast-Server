<?php
class Router{
	public $loggedIn = false;
	public $protocol; 
	public $ip;
	protected $curlInfo;
	protected $requestHeaders = array();
	
	public static function createInstance($cls, $ip, $protocol = 'http'){
		if(!$cls)throw new Exception("No router class supplied");
		if(!class_exists($cls))throw new Exception("$cls router class does not exist");
		$inst = null;
		$s = '$inst = new '.$cls.'();';
		eval($s);
		if($inst){
			$inst->protocol = $protocol; 
			$inst->ip = $ip;
			return $inst;
		} else {
			throw new Exception("Cannot create Router instance of $cls");	
		}
	}
	
	function parseRequest($data, $req, $params, $method){
		$decoded = json_decode($data, true);
		return $decoded;	
	}
	
	function addCurlOptions(&$ch, $req, $params = null, $method = null){
		//stub
		return null;
	}
	
	function addRequestHeader($name, $val){
		$this->requestHeaders[$name] = $val;	
	}
	
	function addRequestHeaders($req, $params, $method){
		//stub
		return null;
	}
	
	function request($req, $params = null, $method = 'GET'){
		$url = $this->protocol.'://'.$this->ip.'/'.$req;
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $url);
		
		if(strtoupper($method) == 'POST' && $params){
			if(is_array($params)){
				$fstring = '';
				foreach($params as $key=>$value) { $fstring .= $key.'='.$value.'&'; }
				rtrim($fstring, '&');
				curl_setopt($ch,CURLOPT_POST, count($params));
				curl_setopt($ch,CURLOPT_POSTFIELDS, $fstring);
			} elseif (is_string($params)) {
				curl_setopt($ch,CURLOPT_POST, 1);
				curl_setopt($ch,CURLOPT_POSTFIELDS, $params);
			}
		} else {
			
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$this->addRequestHeaders($req, $params, $method);
		if($this->requestHeaders && count($this->requestHeaders) > 0){
			$h = array();
			foreach($this->requestHeaders as $k=>$v){
				array_push($h, "$k: $v");
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
		}
		
		$this->addCurlOptions($ch, $req, $params, $method);
		
		$data = curl_exec($ch); 
		$error = curl_error($ch);
		$errno = curl_errno($ch);
		$this->curlInfo = curl_getinfo($ch);
		curl_close($ch);
		    
		if($errno){
			throw new Exception("Request $req resulted cURL error $errno $error");
		}
		if($this->curlInfo['http_code'] == 404){
			throw new Exception("Request $req resulted in router returning http code of 404 and message ".$data);
		}
		
		$data = $this->parseRequest($data, $req, $params, $method);
		return $data;
	}
	
	function getLastRequestInfo($param = null){
		return $param && $this->curlInfo && isset($this->curlInfo[$param]) ? $this->curlInfo[$param] : $this->curlInfo;
	}
	
	function login(){
		$this->loggedIn = true;	
	}
	
	function logout(){
		$this->loggedIn = false;
	}
	
	function reboot(){
		
	}
	
	function getDeviceInfo(){
		
	}
	
	function getWifiInfo(){
		
	}
}
?>