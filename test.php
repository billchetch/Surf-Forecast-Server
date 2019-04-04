<?php
require('_include.php');
$phplib = _SITESROOT_.'services/lib/php/';
require($phplib.'phpmailer/class.phpmailer.php');

$mail = new PHPMailer();
$mail->SetLanguage('en', $phplib.'phpmailer/language/');


//init logger
Logger::init($dbh, array('log_name'=>'test', 'log_options'=>Logger::LOG_TO_SCREEN));
$router = null;
try{
	//mail()
	if(!mail('bill@chetch.net', 'testing', 'test', 'From: info@bulan-baru.com')){
		throw new Exception("Email send failed");
	}
	
} catch (Exception $e){
	if($router && $router->loggedIn){
		$router->logout();
	}
	Logger::exception($e->getMessage());
}
?>