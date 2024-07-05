<?php
require('_include.php');

use chetch\Config as Config;
use chetch\Utils as Utils;
use chetch\sys\Logger as Logger;
use chetch\sys\SysInfo as SysInfo;
use chetch\network\Network as Network;
use chetch\api\APIMakeRequest as APIMakeRequest;

function pingTest($ipType, $ip, &$digest){
	$info = "$ipType: ".($ip ? $ip : " no IP to test");
	echo "Pinging: $info ..\n";
	$rval = false;
	if($ip){
		$stats = Network::ping($ip); //Network::ping($ip);
		if($stats['loss'] == 0){
			$rval = true;
			$info.= " ... responding";
		} else { //this will be complete loss unless we ping with more than the default 1 count
			$val = false;
			$info.= " ... not responding";
		}
	}
	$digest->addDigestInfo("PING TESTS", $info, 1);
	return $rval;
}


try
{
	set_time_limit(60*10); //we allow 10 mins for the script as it has some network latency
	
	$log = Logger::getLog('network check', Logger::LOG_TO_DATABASE + Logger::LOG_TO_SCREEN);
	$log->start();
	

	$si = SysInfo::createInstance();
	$digest = Digest::create("NETWORK CHECK");

	$log->info("Runnning ping tests...");
		
	//we ping test if LAN router, INTERNET router and REMOTE HOST
	$lanRouterAvailable = pingTest("LAN ROUTER IP", Config::get('LAN_ROUTER_IP'), $digest);
	$internetRouterAvailable = pingTest("INTERNET ROUTER IP", Config::get('INTERNET_ROUTER_IP'), $digest);
	$internetAvailable = pingTest("REMOTE HOST", Config::get('PING_REMOTE_HOST'), $digest);
	$log->info("Ping tests: ".$digest->getDigestInfo("PING TESTS"));
	
	$baseURL = Config::get('GPS_API_LOCAL_URL', 'http://localhost:8003/api');
	$apiRequest = APIMakeRequest::createGetRequest($baseURL, "latest-position");
	$serverLocation = $apiRequest->request();
	
	$requests2make = array(); 
	$params = array(); //
	
	$latLon = null;
	if($serverLocation && isset($serverLocation['latitude']) && isset($serverLocation['longitude'])){
		$params = array();
		$params['lat'] = $serverLocation['latitude'];
		$params['lon'] = $serverLocation['longitude'];
		$latLon = $params['lat'].','.$params['lon'];
		$log->info("Using location data $latLon");
	} else {
		throw new Exception("No location data found!");
	}
	
	// sources and locations in one go
	$maxLocations = Config::get('MAX_LOCATIONS', 15);
	$params['max_locations'] = $maxLocations;
	array_push($requests2make, 'sources');
	array_push($requests2make, 'locations');
	$log->info("Getting sources and locations");

	$params['requests'] = implode(',', $requests2make);
	$baseURL = Config::get('API_REMOTE_URL');
	$apiRequest = APIMakeRequest::createGetRequest($baseURL, 'batch', $params);
	$result = $apiRequest->request();
	
	$log->finish();
} catch (Exception $e){
	$log->exception($e->getMessage());
	$log->info("Network check exited because of exception: ".$e->getMessage());
}
?>