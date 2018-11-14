<?php
require('_include.php');

function convertNumber($val, $round = -1){  //scientific exponent
		//e.g. 0.1908054709434509E+1
		$val2return = null;
		$pos = stripos($val, 'E');
		if($pos !== false){
			$n = substr($val, 0, $pos);
			$x = substr($val, $pos);
			$ar = null;
			if(stripos($x,'+') !== false){
				$ar = explode('+', $x);
			} elseif(stripos($x,'-') !== false){
				$ar = explode('-', $x);
				if(count($ar) == 2)$ar[1] = -$ar[1];
			}
			if(!$ar || count($ar) != 2)throw new Exception("Cannot parse exponent $x");
			$val2return = $n * pow(10, $ar[1]);
		} else {
			$val2return = $val;
		}
		if($round > 0 && $val2return != null)$val2return = round($val2return, $round);
		return $val2return;
	}

Logger::init($dbh, array('log_name'=>'test', 'log_options'=>Logger::LOG_TO_SCREEN));
$router = null;
try{
	//$router = Router::createInstance(Config::get('INTERNET_ROUTER_CLASS'), Config::get('INTERNET_ROUTER_IP'));
	//$router->login();
	Logger::start();
	/*$digest = Digest::create($dbh, "TEST");
	$digest->addDigestInfo("-","testing");
	$digest->write();
	
	APIRequest::init($dbh, APIRequest::SOURCE_REMOTE);
	$apiRequest = APIRequest::createRequest('digests');
	$digests = Digest::getStatus(0);
	foreach($digests as $dg){
		$pd = $dg->getPostData();
		echo $pd['source_created'].' '.$pd['source_timezone_offset']."\n";
		$pd = $dg->getPostData();
		$apiRequest->post($pd);
		$dg->setStatus(Digest::STATUS_POSTED);
		$dg->write();
	}*/
	
	$feeds = Feed::createCollection($dbh);
	foreach($feeds as $feed){
		
	}
	
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>