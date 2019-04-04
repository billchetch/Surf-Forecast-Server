<?php
require('_include.php');

//init logger
Logger::init($dbh, array('log_name'=>'test', 'log_options'=>Logger::LOG_TO_SCREEN));
$router = null;
try{
	$to = 'theboat@bulan-baru.com';
	$subject = 'testing from AWS';
	$body = 'a test from AWS';
	$from = 'info@bulan-baru.com';
	$mail = getMailer($to, $subject, $body, $from);
	
	if(!$mail->Send()){
		throw new Exception("Email send failed");
	}
	
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>
