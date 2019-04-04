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
	$errors = array();
	$results = FeedResult::getAlreadyParsed($dbh, Config::get('DELETE_FEED_RESULTS_AFTER_DAYS', 90));
	foreach($results as $result){
		echo "Deleting feed result ".$result->id."\n";
		try{
			$result->delete();
		} catch (Exception $e){
			
		}
	}
	
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>