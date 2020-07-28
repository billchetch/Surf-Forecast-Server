<?php
spl_autoload_register(function ($class) {
	$dir = dirname(__FILE__); //the directory of this script (not the script that includes this)
	
	$class = str_replace("\\", "/", $class);
	
	$paths = array('../common/php/classes', '../../common/php/classes', 'classes');
	if(defined('_CLASS_PATHS_')){
		$paths = array_merge($paths, explode(',', _CLASS_PATHS_));
	}
	
	foreach($paths as $path){
		$classdir = realpath(dirname(__FILE__).'/'.$path);
		if(!is_dir($classdir))continue;

		$fn = $classdir.'/'.$class.'.php';
		if(file_exists($fn)){
			include $fn;
			return;
		}
		
		$it = new RecursiveDirectoryIterator($classdir);
		foreach(new RecursiveIteratorIterator($it) as $file){
			if(basename($file) == $class.'.php'){
				include $file;
				return;
			}
		}
	} //end looping through paths
});


require('_config.php');

use chetch\Config as Config;

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
	$mail->IsSMTP(); // enable SMTP
 	$mail->SMTPDebug = 1; //0 no debug
	$mail->Host = Config::get('SMTP_HOST');
	$mail->SMTPSecure = Config::get('SMTP_SECURE');
	$mail->Port = Config::get('SMTP_PORT');
	$mail->SMTPAuth = true; 
	$mail->SMTPSecure = Config::get('SMTP_SECURE', 'tls');
	$mail->Username = Config::get('SMTP_USERNAME');
	$mail->Password = Config::get('SMTP_PASSWORD');
	$mail->From = Config::get('EMAIL_FROM', 'info@bulan-baru.com');
	
	if($to)$mail->AddAddress($to);
	if($subject)$mail->Subject = $subject;
	if($body)$mail->Body = $body;
	if($from){
		$mail->FromName = $fromName;
		$mail->AddReplyTo($from, $fromName);
	}
	
	return $mail;
}

use chetch\db\DB as DB;
use chetch\sys\Logger as Logger;

try{
	
	date_default_timezone_set('UTC');

	DB::connect(Config::get('DBHOST'), Config::get('DBNAME'), Config::get('DBUSERNAME'), Config::get('DBPASSWORD'));
	DB::setUTC();

	Logger::setLog(basename($_SERVER['PHP_SELF'], ".php"), Logger::LOG_TO_DATABASE);
	

} catch (Exception $e){
	echo "exception: ".$e->getMessage();
	die;
}
?>