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

	$apiURL = "http://sf.bulan-baru.com:8002/api";
	$request = "batch";
	$params = array('requests'=>'x,y');
	$apiRequest = APIMakeRequest::createGetRequest($apiURL, $request, $params);
	$apiRequest->set('request', 'locations');
	$apiRequest->read();

	print_r($apiRequest->getRowData());
	die;


	/*for($i = -5; $i <= 5; $i += 0.5 ){
		echo $i.'='.normalDistribution($i, 1).$lf;
	}
	die;*/

	$lfr = FeedRun::getLastRun();
	$millers = Location::createInstanceFromID(1);
	$sphinx = Location::createInstanceFromID(6);

	
	$location = $millers;
	$f = Forecast::getSynthesis($lfr, $millers);
	//$fs = Forecast::getSynthesis($lfr, $sphinx);

	
	$windBestDirection = 200;
	$swellBestDirection = 35;

	$wd = 332.5;
	$ws = 21.7;

	/*for($i = 0; $i < 360; $i += 15){
		$directionQuality = directionQuality($i, $windBestDirection, 3.5);
		$windQuality = floorQuality($ws, 5, 50, 2*$directionQuality);

		$directionQuality = round($directionQuality, 2);
		$windQuality = round($windQuality, 2);
		echo $i.'='.$directionQuality.','.$windQuality.$lf;
	}
	//echo windQuality($wd, $ws, $windBestDirection);

	die;*/
	//$actualDirection = 359;

		$ratingParams = !empty($location->get('rating_params')) ? json_decode($location->get('rating_params'), true) : null;
		if(!empty($location->get('rating_params')) && json_last_error()){
			throw new Exception("JSON parsing issue occured when trying to parse rating_params: ".json_last_error_msg());
		}
		$swellParams = $ratingParams && !empty($ratingParams['swell']) && !empty($ratingParams['swell']['optimal_direction']) ? $ratingParams['swell'] : null;
		$windParams = $ratingParams && !empty($ratingParams['wind']) && !empty($ratingParams['wind']['optimal_direction']) ? $ratingParams['wind'] : null;
		

		foreach($f['hours'] as $dt=>$h){
		
			$wd = $h['wind_direction']['weighted_average']; //direction
			$ws = $h['wind_speed']['weighted_average'];	//speed in kph
			$wq = Forecast::windQuality($wd, $ws, $windParams['optimal_direction']);


			$sd = $h['swell_direction']['weighted_average']; //degrees
			$sp = $h['swell_period']['weighted_average']; //secs
			$sh = $h['swell_height']['weighted_average']; //meters
			$sq = Forecast::swellQuality($sd, $sp, $sh, $swellParams['optimal_direction']);
			
			$sd = round($sd, 1);
			$sp = round($sp, 1);
			$sh = round($sh, 1);
			$wd = round($wd, 1);
			$ws = round($ws, 1);
			$wq = round($wq, 1);
			$sq = round($sq, 1);

			$sdwa = 0; //$h['swell_direction']['weighted_average']; 
			if(!empty($h['bb_rating']) && abs($h['rating']['weighted_average'] - $h['bb_rating']) >= 2){
				$rating = str_repeat("* ", round($h['rating']['weighted_average']));
				$bbrating = str_repeat("* ", round($h['bb_rating']));
				echo  "$dt: Swell ($sd,$sp,$sh)=$sq, Wind ($wd,$ws)=$wq: $rating | $bbrating".$lf;
			}
		

			/*echo "Original:".$lf;
			echo "SH: ".$h['swell_height']['weighted_average'].$lf;
			echo "SH: ".$h['swell_period']['weighted_average'].$lf;

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
			break;

			break;*/

		}




	//apply adujustment to hours

} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	echo $e->getMessage();
}
?>