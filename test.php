<?php
require('_include.php');

//init logger
Logger::init($dbh, array('log_name'=>'test', 'log_options'=>Logger::LOG_TO_SCREEN));
$router = null;
try{
	$to = 'theboat@bulan-baru.com';
	$subject = 'testing';
	$body = 'a test from';
	$from = 'info@bulan-baru.com';
	$mail = getMailer($to, $subject, $body, $from);
	Logger::info("Sending email to $to from $from");
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
