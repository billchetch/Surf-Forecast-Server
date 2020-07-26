<?php
require_once('_include.php');

//init logger
$router = null;
try{
	$lf = "\n";
	/*$params = array();
	$params['location_id'] = 1;
	$params['source_id'] = 2;
	$feed = Feed::createInstance($params);
	echo "URL: ". $feed->url.$lf;
	echo "Payload: ". $feed->payload;
	$feed->download();
	print_r($feed->data);*/

	$results = FeedResult::createCollection();
	echo "Parsing ".count($results)." results".$lf;
				
				
	foreach($results as $result){
		$d = $result->parse();
		print_r($d);
		echo $lf;
		break;
	}
	die;
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	echo $e->getMessage();
}
?>