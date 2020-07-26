<?php
use chetch\Config as Config;
use \chetch\sys\Logger as Logger;

class FeedRun extends \chetch\db\DBObject{
	public static $feedRun;
	
	
	public $status;
	public $downloadAttempts;
	public $prevId;
	
	/*
	 * static methods
	 */
	public static function initialise(){
		$t =  Config::get('FEED_RUNS_TABLE', 'feed_runs');
		self::setConfig('TABLE_NAME', $t);
		self::setConfig('SELECT_ROW_SQL', "SELECT * FROM $t WHERE status<>'COMPLETED'");
		self::setConfig('SELECT_SQL', "SELECT *, unix_timestamp(now()) - unix_timestamp(created) AS secs FROM $t");
		self::setConfig('SELECT_DEFAULT_FILTER', "status='COMPLETED' AND (error_report IS NULL OR error_report='') AND unix_timestamp(now())-unix_timestamp(created)>=:secs");
		self::setConfig('SELECT_DEFAULT_SORT', 'id DESC');
		self::setConfig('SELECT_DEFAULT_LIMIT', '100');
	}
	
	public static function getLastRun($secs = 0){
		$feedRuns = static::createCollection(array('secs'=>$secs));
		$fr = count($feedRuns) ? $feedRuns[0] : null;
		if(count($feedRuns) > 1)$fr->prevId = $feedRuns[1]->getID();
		return $fr;
	}
	
	public static function run(&$errors){
		$rowdata = array();
		$rowdata['status'] = 'CREATED';
		$feedRun = static::createInstance($rowdata, self::READ_ALL_VALUES);
		
		$log = Logger::getLog();
		$log->info("Starting run with status ".$feedRun->status);
		
		try{
			if($feedRun->status == 'CREATED'){
				$feedRun->setStatus("DOWNLOADING");
			}
			
			if($feedRun->status == 'DOWNLOADING'){
				$statusData = array();
				$feeds = Feed::createCollection();
				$log->info("Downloading ".count($feeds)." feeds");
				
				if(count($feeds) == 0){
					array_push($errors, "No feeds to download");
					$statusData['error_report'] = implode("\n", $errors);
					$feedRun->setStatus('DOWNLOADING', $statusData);
					throw new Exception("No feeds to download");
				}
				
				
				$statusData['download_attempts'] = ++$feedRun->downloadAttempts;
				$statusData['downloading'] = count($feeds);
				$statusData['downloaded'] = 0;
				
				foreach($feeds as $feed){
					if(empty($feed->url))throw new Exception("Feed ID ".$feed->id." has no URL");
					$log->info("Downloading ".$feed->url.'(ID: '.$feed->id.')');
					if($feed->download()){
						try{
							self::$dbh->beginTransaction();
							
							$vals = $feed->getFeedResultValues();
							$vals['feed_run_id'] = $feedRun->id;
							$result = FeedResult::createInstance($vals, false);
							$result->write();
							
							$feed->setRowData(array('last_result_id'=>$result->id, 'last_result'=>self::now(false)));
							$feed->write();
							
							self::$dbh->commit();
							$statusData['downloaded']++;
							$log->info("Downloaded feed and created result ".$result->id);
						} catch (Exception $e){
							array_push($errors, $e->getMessage());
							self::$dbh->rollback();
							$log->exception("Download failure ".$e->getMessage());
						}
					} else {
						//failed to download
						$status = $feed->info['http_code'].': '.$feed->error.' ('.$feed->errno.')';
						if(!empty($feed->info))$status.= " INFO: ".var_export($feed->info, true);
						$log->warning("$status ... Failed to download ".$feed->url);
						array_push($errors, "$status... Failed to download ".$feed->url);
					}
				}
				
				if($statusData['download_attempts'] < Config::get('FEED_RUNS_MAX_DOWNLOAD_ATTEMPTS') && $statusData['downloaded'] != $statusData['downloading']){
					array_push($errors, "Failed to download all feeds");
					$statusData['error_report'] = implode("\n", $errors);
					$feedRun->setStatus('DOWNLOADING', $statusData);
					throw new Exception("Failed to download all feeds");
				} else {
					$statusData['downloaded_on'] = self::now(false);
					if(count($errors))$statusData['error_report'] = implode("\n", $errors);
					$feedRun->setStatus('DOWNLOADED', $statusData);
				}
			}
			
			if($feedRun->status == 'DOWNLOADED'){
				$feedRun->setStatus("PARSING");
			}
			
			if($feedRun->status == 'PARSING'){
				$statusData = array();
				
				$results = FeedResult::createCollection();
				$log->info("Parsing ".count($results)." results");
				
				$statusData['parsing'] = count($results);
				$statusData['parsed'] = 0;
				
				foreach($results as $result){
					try{
						self::$dbh->beginTransaction();
						$forecastData = $result->parse();
						
						if(!$forecastData)throw new Exception("No forecast data returned from parsing");
						
						//we have the forecast data so let's build the objects and then they can write to the database
						$forecastData['feed_run_id'] = $feedRun->id;
						$forecast = Forecast::createInstance($forecastData);
						
						$forecast->write();
						
						self::$dbh->commit();
						$statusData['parsed']++;

						$log->info("Parsed result and created forecast ".$forecast->id);
					} catch (Exception $e){
						self::$dbh->rollback();
						$msg = "Parse failure on result ".$result->id;
						if(!empty($result->responseInfo) && isset($result->responseInfo['url'])){
							$msg.= " when parsing response from ".$result->responseInfo['url'];
						}
						$msg.= ": ".$result->response;
						array_push($errors, "$msg: ".$e->getMessage());
						$log->exception("$msg: ".$e->getMessage());
					}
					
					//whatever happes we record that this has been parsed so we don't process it again next feed run
					$result->set('parsed', 1);
					$result->set('parsed_on', self::now(false));
					$result->write();
				}
				
				$statusData['parsed_on'] = self::now();
				if(count($errors))$statusData['error_report'] = implode("\n", $errors);
				$feedRun->setStatus('PARSED', $statusData);
			}		
	
			if($feedRun->status == 'PARSED'){
				if(count($errors)){
					$statusData['error_report'] = implode("\n", $errors);
					Logger::info("Completed Run with ".count($errors)." errors");
				} else {
					$statusData['error_report'] = '';
					Logger::info("Completed Run successfully");
				}
				$feedRun->setStatus('COMPLETED', $statusData);
			}
			
			return $feedRun;
		} catch (Exception $e){
			$log->exception($e->getMessage());
			throw $e;
		}
	}
	
	
	protected function assignRowData($rowdata){
		parent::assignRowData($rowdata);

		$this->assignR2V($this->status, 'status');
		$this->assignR2V($this->downloadAttempts, 'download_attempts');
	}
	
	public function setStatus($status, $vals = null){
		if(!$vals)$vals = array();
		$vals['status'] = $status;
		$vals['status_updated_on'] = self::now(false);
		$this->setRowData($vals);
		$this->write();
		$this->status = $status;		
	}
}
?>