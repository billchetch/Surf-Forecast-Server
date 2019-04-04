<?php
spl_autoload_register(function ($class) {
    include 'classes/' . $class . '.php';
});
require('_config.php');

if(Config::get('ERROR_REPORTING')){
	error_reporting(Config::get('ERROR_REPORTING'));
}

function getMailer($to = '', $subject = '', $body = '', $from = '', $fromName = ''){
	require(Config::get('PHP_MAILER'));
	
	$s = Config::get('PHP_MAILER');
	$pi = explode('/', $s);
	array_pop($pi);
	$langDir = implode('/', $pi);
	
	$mail = new PHPMailer();
	$mail->SetLanguage('en', $langDir.'/');
	$mail->Host = Config::get('SMTP_HOST');
	$mail->SMTPSecure = Config::get('SMTP_SECURE');
	$mail->Port = Config::get('SMTP_PORT');
	$mail->SMTPAuth = true; 
	$mail->Username = Config::get('SMTP_USERNAME');
	$mail->Password = Config::get('SMTP_PASSWORD');
	
	if($to)$maill->AddAddress($to);
	if($subject)$mail->Subject = $subject;
	if($body)$mail->Body = $body;
	if($from)$mail->From = $from;
	if($from)$mail->FromName = $fromName ? $fromName : $from;
	
	return $mail;
}

$dbh = null;
try{
	
	date_default_timezone_set('UTC');
	
	$host = Config::get('DBHOST');
	$dbname = Config::get('DBNAME');
	$dbh = new PDO("$host;dbname=$dbname", Config::get('DBUSERNAME'), Config::get('DBPASSWORD'));
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
} catch (Exception $e){
	echo "exception: ".$e->getMessage();
	die;
}
?>