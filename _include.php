<?php
spl_autoload_register(function ($class) {
    include 'classes/' . $class . '.php';
});
require('_config.php');

if(Config::get('ERROR_REPORTING')){
	error_reporting(Config::get('ERROR_REPORTING'));
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