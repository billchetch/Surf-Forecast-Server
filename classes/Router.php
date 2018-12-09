<?php
class Router{
	public $loggedIn = false;
	public $protocol; 
	public $ip;
	
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
	
	function parseRequest($data){
		return json_decode($data, true);	
	}
	
	function request($req, $params = null, $method = 'GET'){
		$url = $this->protocol.'://'.$this->ip.'/'.$req;
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $url);
		
		if(strtoupper($method) == 'POST' && $params){
			$fstring = '';
			foreach($params as $key=>$value) { $fstring .= $key.'='.$value.'&'; }
			rtrim($fstring, '&');
			curl_setopt($ch,CURLOPT_POST, count($params));
			curl_setopt($ch,CURLOPT_POSTFIELDS, $fstring);
		} else {
			
		}
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
		
		$data = curl_exec($ch); 
		$error = curl_error($ch);
		$errno = curl_errno($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		    
		if($errno){
			throw new Exception("cURL error $errno $error");
		}
		if($info['http_code'] == 404){
			throw new Exception("Router returned http code of 404 and message ".$data);
		}
		
		$data = $this->parseRequest($data);
		return $data;
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