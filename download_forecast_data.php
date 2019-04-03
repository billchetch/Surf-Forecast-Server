<?php
require_once('_include.php');

Logger::init($dbh, array('log_name'=>'feed run', 'log_options'=>Logger::LOG_TO_DATABASE + Logger::LOG_TO_SCREEN));
	
try{
	set_time_limit(60*60);
	
	//do feed run stuff
	$lastFeedRun = FeedRun::getLastRun($dbh); //last successful feed run (used for digest)
	
	$errors = array();
	$currentFeedRun = FeedRun::run($dbh, $errors);
	
	//now get stuff relating to tidal variation
	Logger::info("Fetch tidal variation info");
	$feeds = Feed::createCollection($dbh);
	$sourceForTideInfo = Config::get('TIDE_INFO_SOURCE_ID', 4);
	$source = Sources::createInstanceFromID($dbh, $sourceForTideInfo);
	
	//build up a list to download
	$feeds2download = array();
	foreach($feeds as $feed){
		if($feed->rowdata['source_id'] == $sourceForTideInfo)array_push($feeds2download, $feed);
	}
	
	foreach($feeds2download as $feed){
		$feed->url.= "&datums";
		Logger::info("Fetching ".$feed->url);
		if($feed->download()){
			$result = $feed->getFeedResultValues();
			$data = json_decode($result['response'], true);
			if(json_last_error()){
				array_push($errors, json_last_error());
				continue;
			}
			if(!isset($data['datums'])){
				array_push($errors, "datums property not found");
				continue;
			}
			foreach($data['datums'] as $datum){
				if($datum['name'] == 'HAT'){
					$locationID = $feed->rowdata['location_id'];
					$location  = Location::createInstanceFromID($dbh, $locationID);
					$location->rowdata['max_tidal_variation'] = $datum['height'];
					$location->write(); 
					Logger::info("Dowloaded and extracted HAT of ".$datum['height']." and saved to location ".$location->rowdata['location']);
				}
			} //end datums loop
		} 
	} //end download loop 
	
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