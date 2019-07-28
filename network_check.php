<?php
require('_include.php');

function pingTest($ipType, $ip, &$digest){
	$info = "$ipType: ".($ip ? $ip : " no IP to test");
	$rval = false;
	if($ip){
		$stats = Utils::ping($ip);
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
	
	Logger::init($dbh, array('log_name'=>'network check', 'log_options'=>Logger::LOG_TO_DATABASE + Logger::LOG_TO_SCREEN));
	Logger::start();
	
	SysInfo::init($dbh);
	$digest = Digest::create($dbh, "NETWORK CHECK");
	
	//we ping test if LAN router, INTERNET router and REMOTE HOST
	$lanRouterAvailable = pingTest("LAN ROUTER IP", Config::get('LAN_ROUTER_IP'), $digest);
	$internetRouterAvailable = pingTest("INTERNET ROUTER IP", Config::get('INTERNET_ROUTER_IP'), $digest);
	$internetAvailable = pingTest("REMOTE HOST", Config::get('PING_REMOTE_HOST'), $digest);
	Logger::info("Ping tests: ".$digest->getDigestInfo("PING TESTS"));
	
	//if the internet router is contactable but there is no internet then we see if we should try a restart
	if($internetAvailable){
		SysInfo::set('internet-last-available', date('Y-m-d H:i:s'));
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
				$dtRA = SysInfo::get('internet-router-last-restarted');
				$routerRestartTime = Config::get('INTERNET_ROUTER_RESTART_TIME', 60*60*24);
				$doReboot = (!$dtRA || (time() - strtotime($dtRA) > $routerRestartTime));
				
				if($doReboot){
					$digest->addDigestInfo("INTERNET ROUTER", "Router available and attempting reboot");
					SysInfo::set('internet-router-last-restarted', date('Y-m-d H:i:s'));
					$router->reboot();
					die;
				} else {
					$router->logout();
					$digest->addDigestInfo("INTERNET ROUTER", "Router available but postponing reboot to a later time. Last rebooted: $dtRA");
				}
			} catch (Exception $e){
				$digest->addDigestInfo("INTERNET ROUTER", 'Exception: '.$e->getMessage());
				Logger::exception($e->getMessage());
			}
		} else {
			Logger::warning("No router class available for status or restart");
			$digest->addDigestInfo("INTERNET ROUTER", 'No router class available for status or restart');
		}
	} else {
		$digest->addDigestInfo("INTERNET ROUTER", "Router not available!");
		Logger::warning("Internet router is not available");	
	}
	
	//If the time is suffucient between updates then we can try and update over network again
	$doNetworkUpdate = false;
	$dt = SysInfo::get('last_updated_from_network');
	if(!$dt || (time() - strtotime($dt) > Config::get('UPDATE_FROM_NETWORK_TIME', 60*60*4))){
		$doNetworkUpdate = true;
	} else {
		Logger::info("No need to update from network as last update was too recent");
	}
	
	//First we get latest GPS location of network and store for reporting purposes
	GPS::init($dbh);
	$serverLocation = GPS::getLatest();
	$coords = GPS::getRecent(3600); //get GPS for last hour
	foreach($coords as $gps){
		$digest->addDigestInfo("GPS", $gps->getSummary(), 1);
	}
	
	if($doNetworkUpdate && $internetAvailable){	
		Logger::info("Updating API cache using data from ".Config::get("API_REMOTE_URL"));
		$t = microtime(true);
		
		//update API cache
		APIRequest::init($dbh, APIRequest::SOURCE_REMOTE);
		$requests2make = array(); //we are going to make a bulk request to the server to save on connections
		$params = array(); //
		
		$latLon = null;
		if($serverLocation && isset($serverLocation->latitude) && isset($serverLocation->longitude)){
			$params = array();
			$params['lat'] = $serverLocation->latitude;
			$params['lon'] = $serverLocation->longitude;
			$latLon = $params['lat'].','.$params['lon'];
			Logger::info("Using location data $latLon");
		} else {
			Logger::info("No location data found!");
		}
		
		// sources and locations in one go
		$params['max_locations'] = Config::get('MAX_LOCATIONS', 10);
		array_push($requests2make, 'sources');
		array_push($requests2make, 'locations');
		Logger::info("Getting sources and locations");
		$params['requests'] = implode(',', $requests2make);
		$data = APIRequest::processGetRequest('batch', $params);
		APIRequest::save2cache($data);
		$dz = APIRequest::getRequestInfo('size_download');
		$totalDownloadSize = $dz; 
		Logger::info("Download size: $dz");
		Logger::info("Saved sources and locations to cache");
		$digest->addDigestInfo("API CACHE UPDATE", "Updated sources and locations ".($latLon ? " using lat/lon $latLon" : ''), 1);
		
		//now get forecasts for those locations
		$requests2make = array();
		$params = array();
		Logger::info("Getting forecasts ".($latLon ? "using locations near $latLon" : 'for all locations'));
		foreach($data['locations'] as $l){
			//$req = 'forecast/'.$l['id'];
			$req = 'forecast-daylight/'.$l['id']; //get only daylight relevant hours
			array_push($requests2make, $req);
		}
		$params['requests'] = implode(',', $requests2make);
		$data = APIRequest::processGetRequest('batch', $params);
		APIRequest::save2cache($data);
		$dz = APIRequest::getRequestInfo('size_download');
		$totalDownloadSize += $dz;  
		Logger::info("Download size: $dz");
		Logger::info("Saved ".count($requests2make)." forecasts to cache");
		$digest->addDigestInfo("API CACHE UPDATE", "Updated ".count($requests2make)." forecasts", 1);
		
		//add some general data to log and digest
		$downloadTime = round(microtime(true) - $t, 2);
		$msg = "Completed update of cache from network $totalDownloadSize bytes in $downloadTime secs";
		Logger::info($msg);
		$digest->addDigestInfo("API CACHE UPDATE", $msg, 1);
		
		//If here we consider this to be a successful update from the network
		SysInfo::set('last_updated_from_network', date('Y-m-d H:i:s'));
	}
	
	//we have a digest for this script so we see if it's sufficient time since the last digest 
	//and if so we save it
	$dt = SysInfo::get('last_network_check_digest');
	if(!$dt || (time() - strtotime($dt) > Config::get('DIGEST_FROM_NETWORK_TIME', 60*60*1))){
		SysInfo::set('last_network_check_digest', date('Y-m-d H:i:s'));
		$digest->write();
		Logger::info("Saving digest");	
	} else {
		Logger::info("Abandoning digest as too recent since last digest");
	}
	
	if($doNetworkUpdate && $internetAvailable){
		//send all outstanding system digests
		APIRequest::init($dbh, APIRequest::SOURCE_REMOTE);
		$apiRequest = APIRequest::createRequest('digests');
		$digests = Digest::getOutstanding();
		$received = Digest::getReceived(); //if received then from within local network so we post to server to be sent on 
		foreach($received as $d)array_push($digests, $d);
		
		Logger::info(count($digests)." outstanding digests....");
		foreach($digests as $dg){
			try{
				$pd = $dg->getPostData();
				$apiRequest->post($pd);
				$dg->setStatus(Digest::STATUS_POSTED);
				$dg->write();
			} catch (Exception $e){
				Logger::exception($e->getMessage());
			}
		}
	}
	
	Logger::info("Completed network check");
} catch (Exception $e){
	Logger::exception($e->getMessage());
	Logger::info("Network check exited because of exception");
}
?>