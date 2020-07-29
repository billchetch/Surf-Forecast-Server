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
	$rval = false;
	if($ip){
		$stats = Network::ping($ip);
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

try{
	set_time_limit(60*10); //we allow 10 mins for the script as it has some network latency
	
	$log = Logger::getLog('network check', Logger::LOG_TO_DATABASE + Logger::LOG_TO_SCREEN);
	$log->start();
	
	$si = SysInfo::createInstance();

	$digest = Digest::create("NETWORK CHECK");
	
	//we ping test if LAN router, INTERNET router and REMOTE HOST
	$lanRouterAvailable = pingTest("LAN ROUTER IP", Config::get('LAN_ROUTER_IP'), $digest);
	$internetRouterAvailable = pingTest("INTERNET ROUTER IP", Config::get('INTERNET_ROUTER_IP'), $digest);
	$internetAvailable = pingTest("REMOTE HOST", Config::get('PING_REMOTE_HOST'), $digest);
	$log->info("Ping tests: ".$digest->getDigestInfo("PING TESTS"));
	
	//if the internet router is contactable but there is no internet then we see if we should try a restart
	if($internetAvailable){
		$si->setData('internet-last-available', SysInfo::now(false));
	} 
	
	if($internetRouterAvailable){
		if(Config::get('INTERNET_ROUTER_CLASS')){
			try{
				$router = Router::createInstance(Config::get('INTERNET_ROUTER_CLASS'), Config::get('INTERNET_ROUTER_IP'));
				$router->login();
				$routerInfo = $router->getDeviceInfo();
				$routerInfo['internet last available'] = SysInfo::get('internet-last-available');
				$digest->addDigestInfo("INTERNET ROUTER", Digest::formatAssocArray($routerInfo));
				
				//now we try restart if more than a certain time has elapsed
				$dtRA = $si->getData('internet-router-last-restarted');
				$routerRestartTime = Config::get('INTERNET_ROUTER_RESTART_TIME', 60*60*24);
				$doReboot = (!$dtRA || (time() - strtotime($dtRA) > $routerRestartTime));
				
				if($doReboot){
					$digest->addDigestInfo("INTERNET ROUTER", "Router available and attempting reboot");
					$si->setData('internet-router-last-restarted', SysInfo::now(false));
					$router->reboot();
					die;
				} else {
					$router->logout();
					$digest->addDigestInfo("INTERNET ROUTER", "Router available but postponing reboot to a later time. Last rebooted: $dtRA");
				}
			} catch (Exception $e){
				$digest->addDigestInfo("INTERNET ROUTER", 'Exception: '.$e->getMessage());
				$log->exception($e->getMessage());
			}
		} else {
			$log->warning("No router class available for status or restart");
			$digest->addDigestInfo("INTERNET ROUTER", 'No router class available for status or restart');
		}
	} else {
		$digest->addDigestInfo("INTERNET ROUTER", "Router not available!");
		$log->warning("Internet router is not available");	
	}
	
	//If the time is suffucient between updates then we can try and update over network again
	$doNetworkUpdate = false;
	$dt = $si->getData('last_updated_from_network');
	if(!$dt || (time() - strtotime($dt) > Config::get('UPDATE_FROM_NETWORK_TIME', 60*60*4))){
		$doNetworkUpdate = true;
	} else {
		$log->info("No need to update from network as last update was too recent");
	}
	
	//First we get latest GPS location of network and store for reporting purposes
	$baseURL = Config::get('GPS_API_LOCAL_URL', 'http://localhost:8003/api');
	$apiRequest = APIMakeRequest::createGetRequest($baseURL, "latest-position");
	$serverLocation = $apiRequest->request();

	if($doNetworkUpdate && $internetAvailable){	
		if(!Config::get("API_REMOTE_URL"))throw new Exception("No API remote URL provided");
	
		$log->info("Updating API cache using data from ".Config::get("API_REMOTE_URL"));
		$t = microtime(true);
		
		//we are going to make a bulk request to the server to save on connections
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
		$apiRequest->writeBatch($result);
		$dz = $apiRequest->info['size_download'];
		$totalDownloadSize = $dz; 
		$log->info("Download size: $dz bytes");
		$log->info("Saved ".count($result['sources'])." sources and ".count($result['locations'])." locations to cache");
		$digest->addDigestInfo("API CACHE UPDATE", "Updated sources and locations ".($latLon ? " using lat/lon $latLon" : ''), 1);
		
		//now get forecasts for those locations
		$requests2make = array();
		$params = array();
		$ep = Config::get('FORECAST_API_ENDPOINT', 'forecast-daylight');
		if($latLon){
			$log->info("Getting forecasts (endpoint: $ep) using $maxLocations locations near $latLon");
		} else {
			$log->info("Getting forecasts (endpoint: $ep) using $maxLocations locations");
		}
		foreach($result['locations'] as $l){
			$req = $ep.'/'.$l['id']; //get only daylight relevant hours
			array_push($requests2make, $req);
		}
		$params['requests'] = implode(',', $requests2make);
		$apiRequest = APIMakeRequest::createGetRequest($baseURL, 'batch', $params);
		$result = $apiRequest->request();
		$apiRequest->writeBatch($result);
		$dz = $apiRequest->info['size_download'];
		$totalDownloadSize += $dz;  
		$log->info("Download size: $dz bytes");
		$log->info("Saved ".count($requests2make)." forecasts to cache");
		$digest->addDigestInfo("API CACHE UPDATE", "Updated ".count($requests2make)." forecasts", 1);
		
		//add some general data to log and digest
		$downloadTime = round(microtime(true) - $t, 2);
		$msg = "Completed update of cache from network $totalDownloadSize bytes in $downloadTime secs";
		$log->info($msg);
		$digest->addDigestInfo("API CACHE UPDATE", $msg, 1);
		
		//If here we consider this to be a successful update from the network
		$si->setData('last_updated_from_network', SysInfo::now(false));
	}
	
	//we have a digest for this script so we see if it's sufficient time since the last digest 
	//and if so we save it
	$dt = $si->getData('last_network_check_digest');
	if(!$dt || (time() - strtotime($dt) > Config::get('DIGEST_FROM_NETWORK_TIME', 60*60*1))){
		$si->setData('last_network_check_digest', SysInfo::now(false));
		$digest->write();
		$log->info("Saving digest");	
	} else {
		$log->info("Abandoning digest as too recent since last digest");
	}
	
	if($doNetworkUpdate && $internetAvailable){
		//send all outstanding system digests
		$digests = Digest::getOutstanding();
		$received = Digest::getReceived(); //if received then from within local network so we post to server to be sent on 
		foreach($received as $d)array_push($digests, $d);
		
		$baseURL = Config::get("API_REMOTE_URL");
		$d2p = min(10, count($digests));
		$log->info("Atempting to post $d2p outstanding digests....");
		for($i = 0; $i < $d2p; $i++){
			$dg = $digests[$i];
			try{
				$pd = $dg->getPostData();
				$apiRequest = APIMakeRequest::createPostRequest($baseURL, 'digest', $pd);
				$apiRequest->request();
				$dg->setStatus(Digest::STATUS_POSTED);
				$dg->write();
				$log->info("Posted digest ".($i + 1));
			} catch (Exception $e){
				$log->exception($e->getMessage());
			}
		}
	}
	
	$log->info("Completed network check");
} catch (Exception $e){
	$log->exception($e->getMessage());
	$log->info("Network check exited because of exception");
}
?>