<?php
require_once('_include.php');

use chetch\api\APIMakeRequest as APIMakeRequest;
use \chetch\Config as Config;
use \chetch\sys\Logger as Logger;

//init logger
$router = null;

function normalDistribution($x, $sd = 1){
	$a = (1.0 / ($sd*sqrt(2*M_PI))); 
	$b = exp(-1*($x*$x) / (2*$sd*$sd));
	return $a*$b;
}

function directionQuality($actualDirection, $bestDirection, $spread = 4){
	$normalisedDirection = abs($bestDirection - $actualDirection);
	if($normalisedDirection > 180)$normalisedDirection = 360 - $normalisedDirection;
	$normalisedDirection = 3.5*$normalisedDirection / 180.0; //bring to within 0, 4

	$normalisedDirection = pow($normalisedDirection, 1.5) / $spread;
	$scaleToOne = sqrt(2*M_PI);
	return $scaleToOne*normalDistribution($normalisedDirection); //, $sd);
}

function ceilingQuality($val, $ceiling, $spread = 4){
	if($val <= 0)return 0;
	if($val >= $ceiling)return 1;

	$normalisedVal = 4.0*($ceiling - $val)/$ceiling;
	$normalisedVal = $normalisedVal*$normalisedVal / $spread;

	$scaleToOne = sqrt(2*M_PI);
	return $scaleToOne*normalDistribution($normalisedVal); 
}

function floorQuality($val, $floor, $max, $spread = 4){
	if($val <= $floor)return 1;
	if($val >= $max)return 0;

	$normalisedVal = ($val - $floor)*4.0/$max;
	$normalisedVal = $normalisedVal*$normalisedVal / $spread;
	$scaleToOne = sqrt(2*M_PI);
	return $scaleToOne*normalDistribution($normalisedVal); 
}


function windQuality($windDirection, $windSpeed, $bestDirection, $windWindow = 4, $windSpeedThreshold = 5, $maxWindspeed = 50){
	$directionQuality = directionQuality($windDirection, $bestDirection, $windWindow);
	$windQuality = floorQuality($windSpeed, $windSpeedThreshold, $maxWindspeed, 1.25*$directionQuality);
	return $windQuality;
}

function swellQuality($swellDirection, $swellPeriod, $swellHeight, $bestDirection, $swellWindow = 1, $periodCeiling = 16, $heightCeiling = 2.5){
	$directionQuality = directionQuality($swellDirection, $bestDirection, $swellWindow);
	$xp = 2;
	$ceiling = pow($periodCeiling, $xp)*$heightCeiling;
	$whq = ceilingQuality(pow($swellPeriod, $xp)*$swellHeight*$directionQuality, $ceiling, 3.5*$swellPeriod/$periodCeiling);
	return $whq;
}

function conditionsRating($swellQuality, $windQuality, $scale = 5){
	 return round($scale*$swellQuality*$windQuality);
}


function download($url, $payload, $encoding){
	//retrieve data
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_HEADER, false); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	if($encoding)curl_setopt($ch, CURLOPT_ENCODING, $this->get('encoding'));
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, Config::get('CURLOPT_CONNECTTIMEOUT',30));
	curl_setopt($ch, CURLOPT_TIMEOUT, Config::get('CURLOPT_TIMEOUT',30));
		
	if(!empty($payload)){
		curl_setopt($ch, CURLOPT_POST, 1);
		//echo $this->payload; die;
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	}
		
	$data = curl_exec($ch); 
	$error = curl_error($ch);
	$errno = curl_errno($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
				
    //store stuff if it's any good
    if($data && $errno == 0 && $info['http_code'] < 400)
	{
        return $data;
    } else {
        throw new Exception($error ? $errno.' '.$error : 'httpcode: '.$info['http_code']);
    }
}


try{
	$lf = "\n";
	$log = Logger::getLog('test', Logger::LOG_TO_SCREEN);
	$log->info('Test started...');

	BMKGParser2::updateModelRun();


	$id= 2;
	$f = Feed::createInstanceFromID($id);

	echo "Feed $id url: ".$f->url.$lf;
	echo "Payload: ".$f->payload.$lf;

	echo "Downloading....".$lf;
	if($f->download()){
		echo "Downloaded!".$lf;

		$pdata = json_decode($f->data, true);
		print_r($pdata);
		//print_r($pdata['time']);
		//print_r($pdata['wdir']);
		//print_r($pdata['wspd']);
		//print_r($pdata['hs']);
		//print_r($pdata['dir']);
	} else {
		echo "Failed to download".$lf;
		print_r($f->error);
		print_r($f->info);
	}

} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	echo $e->getMessage();
}
?>