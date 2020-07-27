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

	$normalisedDirection = $normalisedDirection*$normalisedDirection / $spread;
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


function windQuality($windDirection, $windSpeed, $bestDirection, $windSpeedThreshold = 2, $maxWindspeed = 50){
	$directionQuality = directionQuality($windDirection, $bestDirection, 0.85);
	$windQuality = floorQuality($windSpeed / pow($directionQuality, 2), $windSpeedThreshold, $maxWindspeed, 2);
	return $windQuality;
}

function swellQuality($swellDirection, $swellPeriod, $swellHeight, $bestDirection, $swellWindow = 1, $periodCeiling = 15, $heightCeiling = 2){
	$directionQuality = directionQuality($swellDirection, $bestDirection, $swellWindow);
	$xp = 2;
	$ceiling = pow($periodCeiling, $xp)*$heightCeiling;
	$whq = ceilingQuality(pow($swellPeriod, $xp)*$swellHeight*$directionQuality, $ceiling, 3.5*$swellPeriod/$periodCeiling);
	return $whq;
}

function conditionsRating($swellQuality, $windQuality, $scale = 5){
	 return round($scale*$swellQuality*$windQuality);
}

try{
	$lf = "\n";


	/*$result = FeedResult::createInstanceFromID(59597);

	$data = $result->parse();
	$data['feed_run_id'] = 985;
	//unset($data['hours']);
	//unset($data['days']);
	$f = Forecast::createInstanceFromID($data);
	$f->write();
	die;*/


	/*for($i = -5; $i <= 5; $i += 0.5 ){
		echo $i.'='.normalDistribution($i, 1).$lf;
	}
	die;*/

	$lfr = FeedRun::getLastRun();
	$loc = Location::createInstanceFromID(1);

	$f = Forecast::getSynthesis($lfr, $loc);
	print_r($f['hours']); die;


	$swellKeys = array();
	$swellKeys['height'] = array('swell_height', 'swell_height_primary', 'swell_height_secondary');
	$swellKeys['period'] = array('swell_period', 'swell_period_primary', 'swell_period_secondary');

	$swellAdjustment = array();
	$swellAdjustment['height'] = 1.8;
	$swellAdjustment['period'] = 0.7;
	

	$windAdjustment = array();
	$windAdjustment['speed'] = 0.2;
	
	$windBestDirection = 20;
	$swellBestDirection = 215;

	//$actualDirection = 359;
	echo "Forecast test on location ".$loc->get('location')." with swell direction $swellBestDirection and wind direction $windBestDirection".$lf;
	
	print_r($f);
	die;

	foreach($f['hours'] as $dt=>$h){
		$wd = $h['wind_direction']['weighted_aveage'];
		$ws = $h['wind_speed']['weighted_average'];
		$wq = windQuality($wd, $ws, $windBestDirection);

		echo  $dt.$lf;
		continue;

		/*echo "Original:".$lf;
		print_r($h);

		if($swellAdjustment){
			foreach($swellKeys as $key=>$fields){ 
				if(isset($swellAdjustment[$key])){
					foreach($fields as $field){
						$h[$field]['weighted_values'] *= $swellAdjustment[$key];
						$h[$field]['weighted_average'] *= $swellAdjustment[$key];
					}
				}
			}
		}
		
		if($windAdjustment){
			if(isset($windAdjustment['speed'])){
				$h['wind_speed']['weighted_values'] *= $windAdjustment['speed'];
				$h['wind_speed']['weighted_average'] *= $windAdjustment['speed'];
			}
		}

		echo "Adjusted: ".$lf;
		print_r($h);
		break; */

		break;

	}



	//apply adujustment to hours

} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	echo $e->getMessage();
}
?>