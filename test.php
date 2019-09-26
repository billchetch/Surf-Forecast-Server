<?php
//require_once('_include.php');

//init logger
Logger::init($dbh, array('log_name'=>'test', 'log_options'=>Logger::LOG_TO_SCREEN));
$router = null;
try{
	$id = 21;
	$feed = Feed::createInstanceFromID($dbh, $id);
	if($feed->download()){
		$vals = $feed->getFeedResultValues();
		$r = json_decode($vals['response'], true);
		print_r($r); die;
	} else {
		echo 'poo'; die;
	}
	
	$vals['feed_run_id'] = 54;
	$result = FeedResult::createInstance($dbh, $vals, false);
	$result->write();
	die;
	
	/*$id = 2271;
	
	$results = FeedResult::createCollection($dbh);
	$result = $results[0];
	
	$result->parser = new BMKGParser2();
	print_r($result->parse());
	
	echo "HERE"; die;*/
	
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<script src="http://192.168.0.1/js/crypto-js.js"></script>
<script language="javascript" src="/lib/js/jquery/jquery-1.4.4.min.js"></script>
<script language="javascript">
$(document).ready(function(){
	
});

</script>

</head>
<body>



</body>
</html>
<?php 
die; ?>