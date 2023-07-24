<?php
require_once('_include.php');

use chetch\sys\Logger as Logger;
	
try{
	if(empty($_GET['req']))throw new Exception("No request made");
	$req = $_GET['req'];
	$ar = explode('/', $req);
	$queryString = str_ireplace('req='.$req.'&', '', $_SERVER['QUERY_STRING']);
	$qsParams = array();
	parse_str($queryString, $qsParams);
	$requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);
	
	switch($ar[0]){ 
		case 'api': //API access
			try{
				array_shift($ar);
				$apiCall = implode('/',$ar);
				if(stripos($apiCall, '/') === 0)$apiCall = substr($apiCall, 1);
				$payload = file_get_contents('php://input'); //this is expected to be JSON
				$handler = SurfForecastAPIHandleRequest::createHandler($apiCall, $requestMethod, $qsParams, $payload);
				$handler->handle();
			} catch (Exception $e){
				SurfForecastAPIHandleRequest::exception($e);
				die;
			}
			die;
			break;
			
			
		case 'router':
			require('router.php');
			die;
			break;
			
		case 'cms':
			require('cms.php');
			die;
			break;
			
		case 'test':
			require('test.php');
			break;

		case 'proxy_feed':
			require('proxy_feed.php');
			break;
			
		case 'none':
			break;
			
		default:
			throw new Exception($ar[0]." is an unrecognised service");
			break;
	}

} catch (Exception $e){
	$log = Logger::getLog();
	$log->exception($e->getMessage());
	
	header('HTTP/1.0 404 Not Found', true, 404);
	echo "Exception: ".$e->getMessage();
	die;
}
$message = 'message';
$md5 = md5($message, false);
?>
<html>
<head>
	<script src="http://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.min.js"></script>
	<script src="http://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/md5.min.js"></script>
	
	<style>
  		#windy {
  			width: 100%;
  			height: 300px;
  		}
  	</style>
	<script type="text/javascript">
	window.onload = function(){
		
	}
	</script>
</head>
<body>


    
</body>

</body>
</html>
