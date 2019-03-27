<?php
class Forecast extends DBObject{
	public static $config = array();
	
	public $feedRunID;
	public $sourceID;
	public $locationID;
	public $timezoneOffset;
	
	public $forecastFrom;
	public $forecastTo;
	
	public $hours = array(); //forecast data in the hours table
	public $days = array(); //forecast data in the days table
	
	static public function initialise(){
		$t = Config::get('FORECASTS_TABLE');
		$l = Config::get('LOCATIONS_TABLE');
		static::$config['TABLE_NAME'] = $t;
		$sql = "SELECT f.*, l.timezone, l.timezone_offset, l.latitude, l.longitude FROM $t f INNER JOIN $l l ON f.location_id=l.id";
		static::$config['SELECT_ROW_BY_ID_SQL'] = $sql." WHERE f.id=:id";
		static::$config['SELECT_ROW_SQL'] = $sql." WHERE f.feed_run_id=:feed_run_id AND f.source_id=:source_id AND f.location_id=:location_id";
		static::$config['SELECT_ROWS_SQL'] = $sql." WHERE f.feed_run_id=:feed_run_id AND f.location_id=:location_id";
	}
	
	public static function createCollection($dbh, $params = null){
		$forecasts = parent::createCollection($dbh, $params);
		for($i = 0; $i < count($forecasts); $i++){
			$forecasts[$i]->read();
		}
		return $forecasts;
	}
	
	public static function getSynthesis($dbh, $feedRunID, $location, $weighting = null, $restrict2sources = null){
		if(empty($feedRunID))throw new Exception("Please supply a feed run ID");
		if(empty($location))throw new Exception("Please supply a location");
		$locationID = $location->id;
		$forecastLocationID = !empty($location->rowdata['forecast_location_id']) ? $location->rowdata['forecast_location_id'] : $locationID;
		
		static::initialise();
		$params = array();
		$params['SQL'] = static::$config['SELECT_ROWS_SQL'];
		if($restrict2sources){
			if(is_array($restrict2sources))$restrict2sources = implode(',', $restrict2sources);
			$params['SQL'].= " AND f.source_id IN ($restrict2sources)";
		}
		$params['feed_run_id'] = $feedRunID;
		$params['location_id'] = $forecastLocationID;
		
		$forecasts = static::createCollection($dbh, $params);
		//if there are no forecasts then throw
		if(count($forecasts) == 0){
			throw new Exception("No forecasts found for feed run $feedRunID and location $locationID");
		}
		
		//we build weighting first
		if(empty($weighting))$weighting = array();
		foreach($forecasts as $forecast){
			$sourceID = $forecast->sourceID;
			$weighting[$sourceID] = isset($weighting[$sourceID]) ? $weighting[$sourceID] : 1;
		}
		
		//now we can construct synthesis
		$synthesis = array();
		$synthesis['feed_run_id'] = $feedRunID;
		$synthesis['location_id'] = $locationID;
		$synthesis['weighting'] = $weighting;
		$synthesis['forecast_from'] = null;
		$synthesis['forecast_to'] = null;
		$synthesis['created'] = null;
		$synthesis['hours'] = array();
		$synthesis['days'] = array();
		$synthesis['timezone'] = $forecasts[0]->rowdata['timezone'];
		$synthesis['timezone_offset'] = $forecasts[0]->rowdata['timezone_offset'];
		$synthesis['timezone_offset_secs'] = Utils::timezoneOffsetInSecs($synthesis['timezone_offset']);
		$synthesis['latitude'] = $forecasts[0]->rowdata['latitude'];
		$synthesis['longitude'] = $forecasts[0]->rowdata['longitude'];
		
		$hourCols2weight = array('swell_height', 'swell_height_primary', 'swell_height_secondary', 'swell_period', 'swell_period_primary', 'swell_period_secondary', 'swell_direction', 'swell_direction_primary', 'swell_direction_secondary', 'wind_speed', 'wind_direction', 'tide_height', 'rating');
		$dayCols2weight = array('tide_extreme_height_1', 'tide_extreme_time_1', 'tide_extreme_height_2', 'tide_extreme_time_2', 'tide_extreme_height_3', 'tide_extreme_time_3', 'tide_extreme_height_4', 'tide_extreme_time_4');
		$dayCols2copy = array('tide_extreme_type_1','tide_extreme_type_2','tide_extreme_type_3','tide_extreme_type_4');
		$timeColumns = array('tide_extreme_time_1', 'tide_extreme_time_2', 'tide_extreme_time_3', 'tide_extreme_time_4');
		
		foreach($forecasts as $forecast){
			$tf = strtotime($forecast->rowdata['forecast_from']);
			$tt = strtotime($forecast->rowdata['forecast_to']);
			$tc = strtotime($forecast->rowdata['created']);
			if(!$synthesis['forecast_from'] || $tf < $synthesis['forecast_from'])$synthesis['forecast_from'] = $tf;
			if(!$synthesis['forecast_to'] || $tt > $synthesis['forecast_to'])$synthesis['forecast_to'] = $tt;
			if(!$synthesis['created'] || $tc > $synthesis['created'])$synthesis['created'] = $tc;
			
			//hours first
			foreach($forecast->hours as $key=>$fh){
				if(!isset($synthesis['hours'][$key])){
					$synthesis['hours'][$key] = array();
				}
				
				foreach($hourCols2weight as $col){
					$val = $fh->rowdata[$col];
					
					if(!isset($synthesis['hours'][$key][$col])){
						$synthesis['hours'][$key][$col] = array('weighted_values'=>0, 'weighted_sum'=>0, 'weighted_average'=>null, 'original_values'=>array());
					}
					
					if(empty($val) && $val !== 0){
						continue;
					}
					
					$synthesis['hours'][$key][$col]['original_values'][$forecast->sourceID] = $val;
					
					//convert values if necessary
					if(in_array($col, $timeColumns)){
						$val = strtotime($val);
					}
					
					$weight = $weighting[$forecast->sourceID];
					$weightedValue = $weight * $val;
					$synthesis['hours'][$key][$col]['weighted_values'] += $weightedValue;
					$synthesis['hours'][$key][$col]['weighted_sum'] += $weight;
				}
				
			}
			ksort($synthesis['hours']);
			
			//now days
			foreach($forecast->days as $key=>$fd){
				if(!isset($synthesis['days'][$key])){
					$synthesis['days'][$key] = array();
				}
				
				foreach($dayCols2weight as $col){
					$val = $fd->rowdata[$col];
					
					if(!isset($synthesis['days'][$key][$col])){
						$synthesis['days'][$key][$col] = array('weighted_values'=>0, 'weighted_sum'=>0, 'weighted_average'=>null, 'original_values'=>array());
					}
					
					if(empty($val) && $val !== 0){
						continue;
					}
					
					$synthesis['days'][$key][$col]['original_values'][$forecast->sourceID] = $val;
					
					//convert values if necessary
					if(in_array($col, $timeColumns)){
						$val = strtotime($val);
					}
					
					$weight = $weighting[$forecast->sourceID];
					$weightedValue = $weight * $val;
					$synthesis['days'][$key][$col]['weighted_values'] += $weightedValue;
					$synthesis['days'][$key][$col]['weighted_sum'] += $weight;
				}
				
				foreach($dayCols2copy as $col){
					$synthesis['days'][$key][$col] = $fd->rowdata[$col];
				}
				
				//add first and last light
				$suninfo = date_sun_info(strtotime($fd->forecastDate), $synthesis['latitude'], $synthesis['longitude']);
				$dt = new DateTime(date('Y-m-d H:i:s', $suninfo['civil_twilight_begin']));
				$dt->setTimezone(new DateTimeZone($synthesis['timezone']));
				$synthesis['days'][$key]['first_light'] = $dt->format('Y-m-d H:i:s').' '.$synthesis['timezone_offset'];
				$dt = new DateTime(date('Y-m-d H:i:s', $suninfo['civil_twilight_end']));
				$dt->setTimezone(new DateTimeZone($synthesis['timezone']));
				$synthesis['days'][$key]['last_light'] = $dt->format('Y-m-d H:i:s').' '.$synthesis['timezone_offset'];;
			}
			ksort($synthesis['days']);
		}
		
		
		//calculate averages
		$cols2interpolate = array('swell_height', 'swell_height_primary', 'swell_height_secondary', 'swell_period', 'swell_period_primary', 'swell_period_secondary', 'swell_direction', 'swell_direction_primary', 'swell_direction_secondary', 'wind_speed', 'wind_direction', 'tide_height', 'rating');
		$lastWithValue = array();
		$toInterpolate = array();
		foreach($cols2interpolate as $col){
			$toInterpolate[$col] = array();
			$lastWithValue[$col] = null;
		}
		
		foreach($synthesis['hours'] as $key=>$syn){
			foreach($hourCols2weight as $col){
				if(empty($synthesis['hours'][$key][$col]['weighted_sum']))continue;
				$val = $synthesis['hours'][$key][$col]['weighted_values'] / $synthesis['hours'][$key][$col]['weighted_sum'];
				$synthesis['hours'][$key][$col]['weighted_average'] = $val;
			}
			
			foreach($cols2interpolate as $col){
				if(empty($synthesis['hours'][$key][$col]['weighted_sum'])){
					array_push($toInterpolate[$col], $key);
				} else {
					if(count($toInterpolate[$col]) && $lastWithValue[$col]){
						$lkey = $lastWithValue[$col];
						$startVal = $synthesis['hours'][$lkey][$col]['weighted_average'];
						$endVal = $synthesis['hours'][$key][$col]['weighted_average'];
						$valDiff = $endVal - $startVal;
						$startTime = strtotime($lkey);
						$endTime = strtotime($key);
						$timeDiff = $endTime - $startTime;
						if($timeDiff > 0){
							foreach($toInterpolate[$col] as $tkey){
								$time = strtotime($tkey);
								$newVal = $startVal + $valDiff*(($time - $startTime)/$timeDiff);
								$synthesis['hours'][$tkey][$col]['weighted_average'] = $newVal;
								$synthesis['hours'][$tkey][$col]['original_values'] = 'interpolated';
							}
						}
					}
					$toInterpolate[$col] = array();
					$lastWithValue[$col] = $key;
				}
			}
			
			//format time averages
			foreach($timeColumns as $col){
				if(!isset( $synthesis['hours'][$key][$col]))continue;
				$val = $synthesis['hours'][$key][$col]['weighted_average'];
				if($val){
					$val = date('H:i:s', $val);
					$synthesis['hours'][$key][$col]['weighted_average'] = $val;
				}
			}			
		}
		
		foreach($synthesis['days'] as $key=>$syn){
			foreach($dayCols2weight as $col){
				if(empty($synthesis['days'][$key][$col]['weighted_sum']))continue;
				$val = $synthesis['days'][$key][$col]['weighted_values'] / $synthesis['days'][$key][$col]['weighted_sum'];
				$synthesis['days'][$key][$col]['weighted_average'] = $val;  
			}
			
			//format time averages
			foreach($timeColumns as $col){
				if(!isset( $synthesis['days'][$key][$col]))continue;
				$val = $synthesis['days'][$key][$col]['weighted_average'];
				if($val){
					$val = date('H:i:s', $val);
					$synthesis['days'][$key][$col]['weighted_average'] = $val;
				}
			}
		}
		
		//work out tide positions here as it uses day data to assign a value to hours
		$tz = $synthesis['timezone_offset'];
		foreach($synthesis['hours'] as $key=>$syn){
			$parts = explode(' ', $key);
			$dkey = $parts[0].' '.$tz;
			$synthesis['hours'][$key]['tide_position'] = null;
			
			$echo = false;
			if($key == '2019-03-09 06:00:00 '.$tz){
				$echo = true;
			} else {
				$echo = false;
			}
			
			if(isset($synthesis['days'][$dkey])){
				$day = $synthesis['days'][$dkey];
				$ht = strtotime($parts[0].' '.$parts[1]); //leave out the timezone
				$nextSfx = 0;
				for($i = 1; $i <= 4; $i++){
					$v = $day['tide_extreme_time_'.$i]['weighted_average'];
					$tt = strtotime($parts[0].' '.$v);
					if($tt >= $ht){ // the extreme time is after hour time
						$nextSfx = $i;
						break;	
					}
				}
				
				$prev = null;
				$next = null;
				
				if($nextSfx == 0){ //the hour is later than all the ones for the day so take the 'prev' extreme as the last of the day and the 'next' as the first of the next day
					$prevSfx = $day['tide_extreme_type_4'] ? 4 : 3; //TODO: this should loop until a value is found
					if($day['tide_extreme_type_'.$prevSfx]){
						$prev = array('time'=>$parts[0].' '.$day['tide_extreme_time_'.$prevSfx]['weighted_average'], 'position'=>$day['tide_extreme_type_'.$prevSfx], 'height'=>$day['tide_extreme_height_'.$prevSfx]['weighted_average']);
						$dk = date("Y-m-d", strtotime($prev['time']) + 24*3600);
						if(isset($synthesis['days'][$dk.' '.$tz])){
							$day = $synthesis['days'][$dk.' '.$tz];
							if($day['tide_extreme_type_1']){
								$next = array('time'=>$dk.' '.$day['tide_extreme_time_1']['weighted_average'], 'position'=>$day['tide_extreme_type_1'], 'height'=>$day['tide_extreme_height_1']['weighted_average']);
							}	
						}
					}
				} elseif($nextSfx == 1){ //the hour is earlier than the first so use the first as the 'next' and the 'prev' get from yesterday (if it has data ... if not
					if($day['tide_extreme_type_1']){
						$next = array('time'=>$parts[0].' '.$day['tide_extreme_time_1']['weighted_average'], 'position'=>$day['tide_extreme_type_1'], 'height'=>$day['tide_extreme_height_1']['weighted_average']);
						$dk = date("Y-m-d", strtotime($next['time']) - 24*3600);
						if(isset($synthesis['days'][$dk.' '.$tz])){
							$day = $synthesis['days'][$dk.' '.$tz];
							for($j = 4; $j > 0; $j--){
								$prevSfx = $j;
								if($day['tide_extreme_type_'.$prevSfx]){
									$prev = array('time'=>$dk.' '.$day['tide_extreme_time_'.$prevSfx]['weighted_average'], 'position'=>$day['tide_extreme_type_'.$prevSfx], 'height'=>$day['tide_extreme_height_'.$prevSfx]['weighted_average']);
									break;
								}
							}
						}
					}
				} else { //the hour is somewhere between the first and the last
					$prevSfx = $nextSfx - 1;
					if($day['tide_extreme_type_'.$prevSfx]){
						$prev = array('time'=>$parts[0].' '.$day['tide_extreme_time_'.$prevSfx]['weighted_average'], 'position'=>$day['tide_extreme_type_'.$prevSfx], 'height'=>$day['tide_extreme_height_'.$prevSfx]['weighted_average']);
					}
					if($day['tide_extreme_type_'.$nextSfx]){
						$next = array('time'=>$parts[0].' '.$day['tide_extreme_time_'.$nextSfx]['weighted_average'], 'position'=>$day['tide_extreme_type_'.$nextSfx], 'height'=>$day['tide_extreme_height_'.$nextSfx]['weighted_average']);
					}
				} 
				
				if($prev && $next){
					$pt = strtotime($prev['time']);
					$nt = strtotime($next['time']);
					$position = floor((($ht - $pt) / ($nt - $pt)) * 5) % 5;
					if($prev['position'] == 'HIGH')$position += 5; 
					$synthesis['hours'][$key]['tide_position'] = $position;
				} else {
					//TODO: perhaps generate a position based on data here
				}
				
				//first and last light
			}
		} //end set tide position
		
		$tz = $synthesis['timezone_offset'];
		$synthesis['forecast_from'] = date('Y-m-d H:i:s '.$tz, $synthesis['forecast_from']);
		$synthesis['forecast_to'] = date('Y-m-d H:i:s '.$tz, $synthesis['forecast_to']);
		$synthesis['created'] = date('Y-m-d H:i:s '.self::tzoffset(), $synthesis['created']);
		
		return $synthesis;
	}
	
	public function __construct($forecastData, $readFromDB = self::READ_MISSING_VALUES_ONLY){
		$rowdata = array();
		$fields2copy = array('id', 'feed_run_id', 'location_id', 'source_id', 'forecast_from', 'forecast_to');
		foreach($fields2copy as $f){
			if(isset($forecastData[$f])){
				$rowdata[$f] = $forecastData[$f];
			}	
		}

		parent::__construct($rowdata, $readFromDB);
		
		$this->assignR2V($this->feedRunID, 'feed_run_id');
		$this->assignR2V($this->locationID, 'location_id');
		$this->assignR2V($this->sourceID, 'source_id');
		$this->assignR2V($this->forecastFrom, 'forecast_from');
		$this->assignR2V($this->forecastTo, 'forecast_to');
		$this->assignR2V($this->timezoneOffset, 'timezone_offset');
		
		if(isset($forecastData['hours'])){
			foreach($forecastData['hours'] as $key=>$fd){
				$fhours = ForecastHour::createInstance(self::$dbh, $fd, false);
				if(isset($this->hours[$key]))$fhours->setID($this->hours[$key]->id);
				$this->hours[$key] = $fhours;
			}
		}
		if(isset($forecastData['days'])){
			foreach($forecastData['days'] as $key=>$fd){
				$fdays = ForecastDay::createInstance(self::$dbh, $fd, false);
				if(isset($this->days[$key]))$fdays->setID($this->days[$key]->id);
				$this->days[$key] = $fdays; 
			}
		}
	}
	
	public function write(){
		$outerTransaction = self::$dbh->inTransaction();
		try{
			if(!$outerTransaction)self::$dbh->beginTransaction();
			$forecastID = parent::write();
			foreach($this->hours as $key=>$fh){
				$fh->setForecastID($forecastID);
				$fh->write();
			}
			foreach($this->days as $key=>$fd){
				$fd->setForecastID($forecastID);
				$fd->write();
			}
			if(!$outerTransaction)self::$dbh->commit();
		} catch (Exception $e){
			if(!$outerTransaction)self::$dbh->rollback();
			throw $e;
		}
	}
	
	public function read(){
		parent::read();
		
		if(!empty($this->id)){
			$tz = $this->rowdata['timezone_offset'];
		
			$params = array('forecast_id'=>$this->id);
			$hours = ForecastHour::createCollection(self::$dbh, $params);
			foreach($hours as $fh){
				$key = $fh->key.' '.$tz;
				$this->hours[$key] = $fh;
			}
			
			$days = ForecastDay::createCollection(self::$dbh, $params);
			foreach($days as $fd){
				$key = $fd->key.' '.$tz;
				$this->days[$key] = $fd;
			}
		}
	}
}

class ForecastDetail extends DBObject{
	public static $config = array();
	
	public $forecastID;
	public $key;
	
	public function __construct($rowdata, $readFromDB = self::READ_MISSING_VALUES_ONLY){
		parent::__construct($rowdata, $readFromDB);

		$this->assignR2V($this->forecastID, 'forecast_id');
	}
	
	public function setForecastID($forecastID){
		$this->forecastID = $forecastID;
		if(isset($this->rowdata))$this->rowdata['forecast_id'] = $this->forecastID;
	}
}

class ForecastHour extends ForecastDetail{
	public static $config = array();
	
	public $forecastDate;
	public $forecastTime;
	
	static public function initialise(){
		$t = Config::get('FORECAST_HOURS_TABLE');
		static::$config['TABLE_NAME'] = $t;
		
		$sql = "SELECT * FROM $t WHERE forecast_id=:forecast_id"; 
		static::$config['SELECT_ROWS_SQL'] = $sql;
	}
	
	public function __construct($rowdata, $readFromDB = self::READ_MISSING_VALUES_ONLY){
		parent::__construct($rowdata, $readFromDB);

		$this->assignR2V($this->forecastDate, 'forecast_date');
		$this->assignR2V($this->forecastTime, 'forecast_time');
		
		$this->key = $this->forecastDate.' '.$this->forecastTime;
	}
}

class ForecastDay extends ForecastDetail{
	public static $config = array();
	
	public $forecastDate;
	
	static public function initialise(){
		$t = Config::get('FORECAST_DAYS_TABLE');
		static::$config['TABLE_NAME'] = $t;
		
		$sql = "SELECT * FROM $t WHERE forecast_id=:forecast_id"; 
		static::$config['SELECT_ROWS_SQL'] = $sql;
	}
	
	public function __construct($rowdata, $readFromDB = self::READ_MISSING_VALUES_ONLY){
		parent::__construct($rowdata, $readFromDB);

		$this->assignR2V($this->forecastDate, 'forecast_date');
		
		$this->key = $this->forecastDate;
	}
}
?>