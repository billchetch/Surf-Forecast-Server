<?php
require_once('_include.php');

Logger::init($dbh, array('log_name'=>'feed run', 'log_options'=>Logger::LOG_TO_DATABASE + Logger::LOG_TO_SCREEN));
	
try{
	set_time_limit(60*60);
	
	$lastFeedRun = FeedRun::getLastRun($dbh); //last successful feed run (used for digest)
	
	$errors = array();
	$currentFeedRun = FeedRun::run($dbh, $errors);
	
	Logger::info("Creating digest...");
	$digest = Digest::create($dbh, "DOWNLOAD FORECAST DATA");
	if($lastFeedRun){
		$s = Digest::formatAssocArray($lastFeedRun->rowdata);
		$digest->addDigestInfo("LAST SUCCESSFUL RUN", $s);
	}
	if($currentFeedRun){
		$s = Digest::formatAssocArray($currentFeedRun->rowdata);
		$digest->addDigestInfo("CURRENT RUN", $s);
	}
	$digest->write();
	Logger::info("Created digest");
	
	if($errors && count($errors)){
		throw new Exception(var_export($errors, true));
	}	
	
} catch (Exception $e){
	Logger::exception($e->getMessage());
	if(Config::get('EMAIL_EXCEPTIONS_TO')){
		mail(Config::get('EMAIL_EXCEPTIONS_TO'), 'Download forecast data exception', $e->getMessage());
	}
}

?>