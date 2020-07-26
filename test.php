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

	$feeds = Feed::createCollection();
	echo "Downloading ".count($feeds)." feeds".$lf;
				
	foreach($feeds as $feed){
		echo $feed->url.$lf;
	}
	die;
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>