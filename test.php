<?php
require('_include.php');

//init logger
Logger::init($dbh, array('log_name'=>'test', 'log_options'=>Logger::LOG_TO_SCREEN));
$router = null;
try{
	$to = 'bill@chetch.net';
	$subject = 'test';
	$body = 'test';
	$mail = getMailer($to, $subject, $body);
	var_dump($mail); die;
	
	if($mail->Send()){
		throw new Exception("Email send failed");
	}
	
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>