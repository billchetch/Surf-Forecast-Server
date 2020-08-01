<?php
require_once('_include.php');

use \chetch\Config as Config;
use \chetch\sys\Logger as Logger;

Logger::setLog('feed run', Logger::LOG_TO_DATABASE + Logger::LOG_TO_SCREEN);
$log = Logger::getLog();
	
try{
	set_time_limit(60*60);
	ini_set("memory_limit","2048M");
	
	//get log and start this show
	$log->start();
	
	//do feed run stuff
	$lastFeedRun = FeedRun::getLastRun(); //last successful feed run (used for digest)
	
	$errors = array();
	$currentFeedRun = null;
	try{
		$currentFeedRun = FeedRun::run($errors);
	} catch (Exception $e){
		array_push($errors, $e->getMessage());
	}
	
	//now get stuff relating to tidal variation
	$log->info("Fetch tidal variation info");
	$feeds = Feed::createCollection();
	$sourceForTideInfo = Config::get('TIDE_INFO_SOURCE_ID', 4);
	$source = Sources::createInstanceFromID($sourceForTideInfo);
	
	//build up a list to download
	$feeds2download = array();
	foreach($feeds as $feed){
		if($feed->get('source_id') == $sourceForTideInfo)array_push($feeds2download, $feed);
	}
	
	$locationsUpdatedCount = 0;
	foreach($feeds2download as $feed){
		$feed->url.= "&datums";
		$log->info("Fetching ".$feed->url);
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
					$locationID = $feed->get('location_id');
					$location  = Location::createInstanceFromID($locationID);
					$location->set('max_tidal_variation', $datum['height']);
					$location->write(); 
					$log->info("Dowloaded and extracted HAT of ".$datum['height']." and saved to location ".$location->get('location'));
					$locationsUpdatedCount++;
				}
			} //end datums loop
		} 
	} //end download tidal variations for locations loop

	//delete old feed results
	$deletedFeedResultsCount = 0;
	$daysOld = Config::get('DELETE_FEED_RESULTS_AFTER_DAYS', 90);
	$results = FeedResult::getAlreadyParsed($daysOld);
	$log->info("Deleting ".count($results)." parsed feed results more than $daysOld days old");
	foreach($results as $result){
		try{
			$result->delete();
			$deletedFeedResultsCount++;
		} catch (Exception $e){
			array_push($errors, $e->getMessage());
		}
	}
	
	$log->info("Creating digest...");
	$digest = Digest::create( "DOWNLOAD FORECAST DATA");
	if($lastFeedRun){
		$s = Digest::formatAssocArray($lastFeedRun->getRowData());
		$digest->addDigestInfo("PREVIOUS SUCCESSFULLY COMPLETED RUN", $s);
	}
	if($currentFeedRun){
		$s = Digest::formatAssocArray($currentFeedRun->getRowData());
		$digest->addDigestInfo("CURRENT RUN", $s);
	}
	if(count($errors)){
		$s = Digest::formatAssocArray($errors);
		$digest->addDigestInfo("ERRORS", $s);
	}
	
	$digest->addDigestInfo("LOCATIONS UPDATED WITH MAX TIDAL VARIATION", $locationsUpdatedCount);
	$digest->addDigestInfo("FEED RESULTS DELETED", $deletedFeedResultsCount);
	
	$digest->write();
	$log->info("Created digest");
	
	if($errors && count($errors)){
		throw new Exception(var_export($errors, true));
	}	
	
} catch (Exception $e){
	$log->exception($e->getMessage());
	if(Config::get('EMAIL_EXCEPTIONS_TO')){
		mail(Config::get('EMAIL_EXCEPTIONS_TO'), 'Download forecast data exception', $e->getMessage());
	}
}

?>