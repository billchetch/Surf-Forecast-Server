<?php
require_once('_include.php');

try{
	if(empty($_GET['req']))throw new Exception("No request made");
	$req = $_GET['req'];
	$ar = explode('/', $req);
	
	switch($ar[0]){ //API
		case 'api':
			try{
				array_shift($ar);
				$apiRequest = implode('/',$ar);
				if(stripos($apiRequest, '/') === 0)$apiRequest = substr($apiRequest, 1);
				$queryString = str_ireplace('req='.$req.'&', '', $_SERVER['QUERY_STRING']);
				$params = array(); //
				parse_str($queryString, $params);
				
				APIRequest::init($dbh, Config::get('API_SOURCE'));
				$requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);
				$allow = Config::getAsArray('API_ALLOW_REQUESTS', 'GET');
				if(!in_array($requestMethod, $allow))throw new Exception("Cannot $requestMethod to this API ".$_SERVER['REQUEST_URI']);
				
				switch($requestMethod){
					case 'GET':
						APIRequest::setUTC();
						$data = APIRequest::processGetRequest($apiRequest, $params);
						break;
						
					case 'PUT':
						$data = file_get_contents('php://input'); //this is expected to be JSON
						$data = json_decode($data, true);
						$params = array_merge($params, $data);
						$data = APIRequest::processPutRequest($apiRequest, $params);
						break;
						
					case 'POST':
						$data = file_get_contents('php://input'); //this is expected to be JSON
						$data = json_decode($data, true);
						$params = array_merge($params, $data);
						$data = APIRequest::processPostRequest($apiRequest, $params);
						break;
						
					case 'DELETE':
						$data = APIRequest::processDeleteRequest($apiRequest, $params);
						break;
				}
				 
				//output
				APIRequest::output($data);
					
			} catch (Exception $e){
				header('HTTP/1.0 404 Not Found', true, 404);
				echo "API exception: ".$e->getMessage();
				die;
			}
			break;
			
			
		case 'router':
			require('router.php');
			break;
			
		case 'cms':
			require('cms.php');
			break;
			
		default:
			throw new Exception("Unrecognised service");
			break;
	}

} catch (Exception $e){
	if($dbh){
		Logger::init($dbh, array('log_name'=>'http request', 'log_options'=>Logger::LOG_TO_DATABASE));
		Logger::exception($e->getMessage());
	}
	
	header('HTTP/1.0 404 Not Found', true, 404);
	echo "Exception: ".$e->getMessage();
}

?>