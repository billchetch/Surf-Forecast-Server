<?php
require_once('_include.php');

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


use chetch\api\APIMakeRequest as APIMakeRequest;

try{
	$lf = "\n";
	echo 'here';

} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	echo $e->getMessage();
}
?>