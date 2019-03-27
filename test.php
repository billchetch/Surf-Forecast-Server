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

//init logger
Logger::init($dbh, array('log_name'=>'test', 'log_options'=>Logger::LOG_TO_SCREEN));
$router = null;
try{
	/*$cls = 'TPLinkM7350Router';
	$ip = '192.168.0.1';
	$router = Router::createInstance($cls, $ip);
	
	$router->login();
	$data = $router->getDeviceInfo();
	Digest::initialise();
	echo Digest::formatAssocArray($data);
	$router->logout();
	die;*/
	
	$tz = '+0800';
	echo Utils::timezoneOffsetInSecs($tz);
	
	
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>