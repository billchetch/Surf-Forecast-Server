<?php
use chetch\Config as Config;
use chetch\Utils as Utils;
use \Exception as Exception;

class Forecast extends \chetch\db\DBObject{
	static public function initialise(){
		$t = Config::get('FORECASTS_TABLE', 'sf_forecasts');
		$l = Config::get('LOCATIONS_TABLE', 'sf_locations');
		static::setConfig('TABLE_NAME', $t);
		$sql = "SELECT f.*, l.max_tidal_variation, l.timezone, l.timezone_offset, l.latitude, l.longitude FROM $t f INNER JOIN $l l ON f.location_id=l.id";
		static::setConfig('SELECT_SQL', $sql);
		static::setConfig('SELECT_DEFAULT_FILTER', "f.feed_run_id=:feed_run_id AND f.location_id=:location_id");
		static::setConfig('SELECT_ROW_BY_ID_SQL', $sql." WHERE f.id=:id");
		static::setConfig('SELECT_ROW_SQL', $sql." WHERE f.feed_run_id=:feed_run_id AND f.source_id=:source_id AND f.location_id=:location_id");	
	}

	static public function getForecast($feedRunID, $sourceID, $location){
		$forecasts = self::getForecasts($feedRunID, $location);
		foreach($forecasts as $f){
			if($f->get('source_id') == $sourceID){
				return $f->asAssocArray(true);
			}
		}
		return null;
	}

	static public function getForecasts($feedRunID, $location){
		$locationID = $location->getID();
		$forecastLocationID = !empty($location->get('forecast_location_id')) ? $location->get('forecast_location_id') : $locationID;

		$params = array();
		$params['feed_run_id'] = $feedRunID;
		$params['location_id'] = $forecastLocationID;
		return static::createCollection($params);
	}
	
	public static function getSynthesis($feedRun, $location, $weighting = null){
		$feedRunID = $feedRun->getID();
		if(empty($feedRunID))throw new Exception("Please supply a feed run ID");
		if(empty($location))throw new Exception("Please supply a location");
		$locationID = $location->getID();

		$forecasts = static::getForecasts($feedRunID, $location);
		
		//if there are no forecasts then throw
		if(count($forecasts) == 0){
			throw new Exception("No forecasts found for feed run $feedRunID and location $locationID");
		}
		
		//we build weighting
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
		$synthesis['max_tidal_variation'] = $forecasts[0]->get('max_tidal_variation');
		$synthesis['timezone'] = $forecasts[0]->get('timezone');
		$synthesis['timezone_offset'] = $forecasts[0]->get('timezone_offset');
		$synthesis['timezone_offset_secs'] = Utils::timezoneOffsetInSecs($synthesis['timezone_offset']);

		$synthesis['latitude'] = $forecasts[0]->get('latitude');
		$synthesis['longitude'] = $forecasts[0]->get('longitude');
		
		$hourCols2weight = array('swell_height', 'swell_height_primary', 'swell_height_secondary', 'swell_period', 'swell_period_primary', 'swell_period_secondary', 'swell_direction', 'swell_direction_primary', 'swell_direction_secondary', 'wind_speed', 'wind_direction', 'tide_height', 'rating');
		$dayCols2weight = array('tide_extreme_height_1', 'tide_extreme_time_1', 'tide_extreme_height_2', 'tide_extreme_time_2', 'tide_extreme_height_3', 'tide_extreme_time_3', 'tide_extreme_height_4', 'tide_extreme_time_4');
		$dayCols2copy = array('tide_extreme_type_1','tide_extreme_type_2','tide_extreme_type_3','tide_extreme_type_4');
		$timeColumns = array('tide_extreme_time_1', 'tide_extreme_time_2', 'tide_extreme_time_3', 'tide_extreme_time_4');
		
		foreach($forecasts as $forecast){
			$forecast->addDetails();

			$tf = strtotime($forecast->get('forecast_from'));
			$tt = strtotime($forecast->get('forecast_to'));
			$tc = strtotime($forecast->get('created'));
			if(!$synthesis['forecast_from'] || $tf < $synthesis['forecast_from'])$synthesis['forecast_from'] = $tf;
			if(!$synthesis['forecast_to'] || $tt > $synthesis['forecast_to'])$synthesis['forecast_to'] = $tt;
			if(!$synthesis['created'] || $tc > $synthesis['created'])$synthesis['created'] = $tc;
			
			//hours first
			foreach($forecast->hours as $key=>$fh){
				if(!isset($synthesis['hours'][$key])){
					$synthesis['hours'][$key] = array();
				}
				
				foreach($hourCols2weight as $col){
					$val = $fh->get($col);
					
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
					$val = $fd->get($col);
					
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
					$synthesis['days'][$key][$col] = $fd->get($col);
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
			}
		} //end set tide position

		//add final top level data
		$tz = $synthesis['timezone_offset'];
		$synthesis['forecast_from'] = date('Y-m-d H:i:s '.$tz, $synthesis['forecast_from']);
		$synthesis['forecast_to'] = date('Y-m-d H:i:s '.$tz, $synthesis['forecast_to']);
		$synthesis['created'] = date('Y-m-d H:i:s '.self::tzoffset(), $synthesis['created']);

		//now we add location specific 'adujstments'
		$swellAdjustment = null;
		if(!empty($location->get('swell_adjustment'))){
			$swellAdjustment = json_decode($location->get('swell_adjustment'), true);
			if(json_last_error()){
				throw new Exception("JSON parsing issue occured when trying to parse swell_adjustment: ".json_last_error_msg());
			}
		}
		$windAdjustment = null;
		if(!empty($location->get('wind_adjustment'))){
			$windAdjustment = json_decode($location->get('wind_adjustment'), true);
			if(json_last_error()){
				throw new Exception("JSON parsing issue occured when trying to parse wind_adjustment: ".json_last_error_msg());
			}
		}

		if($swellAdjustment || $windAdjustment){
			$synthesis['adusted'] = 1;
			$swellKeys = array();
			$swellKeys['height'] = array('swell_height', 'swell_height_primary', 'swell_height_secondary');
			$swellKeys['period'] = array('swell_period', 'swell_period_primary', 'swell_period_secondary');

			foreach($synthesis['hours'] as &$h){
				if($swellAdjustment){
					foreach($swellKeys as $key=>$fields){ 
						if(isset($swellAdjustment[$key])){
							foreach($fields as $field){
								$h[$field]['weighted_values'] *= $swellAdjustment[$key];
								$h[$field]['weighted_average'] *= $swellAdjustment[$key];
							}
						}
					}
				}
		
				if($windAdjustment){
					if(isset($windAdjustment['speed'])){
						$h['wind_speed']['weighted_values'] *= $windAdjustment['speed'];
						$h['wind_speed']['weighted_average'] *= $windAdjustment['speed'];
					}
				}
			} //end adjusting 'hours'
		} else {
			$synthesis['adjusted'] = 0;
		}//end adjusstments

		//finally we give our own rating
		$ratingParams = self::getRatingParams($location);
		if($ratingParams['swell']['optimal_direction'] && $ratingParams['wind']['optimal_direction']){
			foreach($synthesis['hours'] as $dt=>$h){
				//Evaluate Wind
				$wd = $h['wind_direction']['weighted_average']; //direction
				$ws = $h['wind_speed']['weighted_average'];	//speed in kph
				if($wd === null || $ws === null)continue;

				$vals = $ratingParams['wind'];
				$wq = self::windQuality($wd, $ws, $vals['optimal_direction'], $vals['window'], $vals['speed_floor'], $vals['speed_max']);

				//Evaluate Swell
				$sd = $h['swell_direction']['weighted_average']; //degrees
				$sp = $h['swell_period']['weighted_average']; //secs
				$sh = $h['swell_height']['weighted_average']; //meters
				if($sd === null || $sp === null || $sh === null)continue;

				//$swellWindow = 1, $periodCeiling = 15, $heightCeiling = 2.5){
				$vals = $ratingParams['swell'];
				
				$sq = self::swellQuality($sd, $sp, $sh, $vals['optimal_direction'], $vals['window'], $vals['period_ceiling'], $vals['height_ceiling']);

				$rating = self::conditionsRating($sq, $wq);
				$synthesis['hours'][$dt]['bb_rating'] = $rating;
			}
		}


		return $synthesis;
	}
	
	
	private static function combineArrays($ar1, $ar2, $key = ''){
		if(!is_array($ar1))return $ar1;
		
		foreach($ar2 as $key=>$val){
			if(!isset($ar1[$key]) || $ar1[$key] == ''){
				$ar1[$key] = $val;
			} else {
				if(is_array($val)){
					$ar1[$key] = self::combineArrays($ar1[$key], $ar2[$key], $key);
				}
			}
		}
		
		ksort($ar1);
		return $ar1;
	}
	
	//$syn1 takes priority
	public static function combineForecasts($f1, $f2){
		//we only check days and hours
		$f1['days'] = self::combineArrays($f1['days'], $f2['days']);
		$f1['hours'] = self::combineArrays($f1['hours'], $f2['hours']);
		
		return $f1;
	}
	

	public static function normalDistribution($x, $sd = 1){
		$a = (1.0 / ($sd*sqrt(2*M_PI))); 
		$b = exp(-1*($x*$x) / (2*$sd*$sd));
		return $a*$b;
	}

	public static function directionQuality($actualDirection, $bestDirection, $spread = 4){
		$normalisedDirection = abs($bestDirection - $actualDirection);
		if($normalisedDirection > 180)$normalisedDirection = 360 - $normalisedDirection;
		$normalisedDirection = 3.5*$normalisedDirection / 180.0; //bring to within 0, 4

		$normalisedDirection = pow($normalisedDirection, 1.5) / $spread;
		$scaleToOne = sqrt(2*M_PI);
		return $scaleToOne*self::normalDistribution($normalisedDirection); //, $sd);
	}

	protected static function ceilingQuality($val, $ceiling, $spread = 4){
		if($val <= 0)return 0;
		if($val >= $ceiling)return 1;

		$normalisedVal = 4.0*($ceiling - $val)/$ceiling;
		$normalisedVal = $normalisedVal*$normalisedVal / $spread;

		$scaleToOne = sqrt(2*M_PI);
		return $scaleToOne*self::normalDistribution($normalisedVal); 
	}

	protected static function floorQuality($val, $floor, $max, $spread = 4){
		if($val <= $floor)return 1;
		if($val >= $max)return 0;

		$normalisedVal = ($val - $floor)*4.0/$max;
		$normalisedVal = $normalisedVal*$normalisedVal / $spread;
		$scaleToOne = sqrt(2*M_PI);
		return $scaleToOne*self::normalDistribution($normalisedVal); 
	}


	public static function windQuality($windDirection, $windSpeed, $bestDirection, $windWindow = 4, $windSpeedFloor = 8, $windSpeedMax = 50){
		$directionQuality = self::directionQuality($windDirection, $bestDirection, $windWindow);
		$windQuality = self::floorQuality($windSpeed, $windSpeedFloor, $windSpeedMax, 1.25*$directionQuality);
		return $windQuality;
	}

	public static function swellQuality($swellDirection, $swellPeriod, $swellHeight, $bestDirection, $swellWindow = 1, $periodCeiling = 15, $heightCeiling = 2.5){
		$directionQuality = self::directionQuality($swellDirection, $bestDirection, $swellWindow);
		$xp = 2;
		$ceiling = pow($periodCeiling, $xp)*$heightCeiling;
		$whq = self::ceilingQuality(pow($swellPeriod, $xp)*$swellHeight*$directionQuality, $ceiling, 3.5*$swellPeriod/$periodCeiling);
		return $whq;
	}

	public static function conditionsRating($swellQuality, $windQuality, $scale = 5){
		 return round($scale*$swellQuality*$windQuality);
	}

	public static function getRatingParams($location){
		if(empty($location->get('rating_params')))return null;

		$ratingParams = json_decode($location->get('rating_params'), true);
		if(json_last_error()){
			throw new Exception("JSON parsing issue occured when trying to parse rating_params: ".json_last_error_msg());
		}
		
		//TODO: make these 'regional' ...atm only for Indo
		$defaults = array();
		$defaults['swell'] = array('optimal_direction'=>null, 'period_ceiling'=>15, 'height_ceiling'=>2.25, 'window'=>1);
		$defaults['wind'] = array('optimal_direction'=>null, 'speed_floor'=>5, 'speed_max'=>50, 'window'=>4);
		
		$params2return = array('wind'=>array(), 'swell'=>array());
		foreach($params2return as $key=>$ar){
			foreach($defaults[$key] as $k=>$defaultValue){
				$params2return[$key][$k] = isset($ratingParams[$key]) && isset($ratingParams[$key][$k]) ? $ratingParams[$key][$k] : $defaultValue;
			}
		}

		return $params2return;
	}


	//Instance fiellds and methods
	public $feedRunID;
	public $sourceID;
	public $locationID;
	public $timezoneOffset;
	
	public $forecastFrom;
	public $forecastTo;
	
	public $hours = array(); //forecast data in the hours table
	public $days = array(); //forecast data in the days table

	protected function assignRowData($rowdata){
		parent::assignRowData($rowdata);
		
		$this->assignR2V($this->feedRunID, 'feed_run_id');
		$this->assignR2V($this->locationID, 'location_id');
		$this->assignR2V($this->sourceID, 'source_id');
		$this->assignR2V($this->forecastFrom, 'forecast_from');
		$this->assignR2V($this->forecastTo, 'forecast_to');
		$this->assignR2V($this->timezoneOffset, 'timezone_offset');

		if(isset($rowdata['hours']) && is_array($rowdata['hours'])){
			foreach($rowdata['hours'] as $key=>$fd){
               $fhours = ForecastHour::createInstance($fd, false);
               if(isset($this->hours[$key]))$fhours->setID($this->hours[$key]->id);
			   $this->hours[$key] = $fhours;
            }
		}

		if(isset($rowdata['days']) && is_array($rowdata['days'])){
			foreach($rowdata['days'] as $key=>$fd){
                $fdays = ForecastDay::createInstance($fd, false);
                if(isset($this->days[$key]))$fdays->setID($this->days[$key]->id);
                $this->days[$key] = $fdays;
            }
		}
	}
	
	public function write($readAgain = false){
		$outerTransaction = self::$dbh->inTransaction();
		try{
			if(!$outerTransaction)self::$dbh->beginTransaction();
			$forecastID = parent::write();
			if(!$forecastID)throw new Exception("No forecast ID returned from Forecast::write");

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
	
	public function addDetails(){
		if(!empty($this->getID())){
			$this->hours = array();
			$this->days = array();
			$tz = $this->get('timezone_offset');
		
			$params = array('forecast_id'=>$this->getID());
			$hours = ForecastHour::createCollection($params);
			foreach($hours as $fh){
				$key = $fh->key.' '.$tz;
				$this->hours[$key] = $fh;
			}
			
			$days = ForecastDay::createCollection($params);
			foreach($days as $fd){
				$key = $fd->key.' '.$tz;
				$this->days[$key] = $fd;
			}
		}
	}

	public function asAssocArray($addDetails = true){
		$ar = $this->getRowData();
		
		if($addDetails){
			$this->addDetails();
			$ar['hours'] = array();
			foreach($this->hours as $k=>$v){
				$ar['hours'][$k] = $v->getRowData();
			}
			$ar['days'] = array();
			foreach($this->days as $k=>$v){
				$ar['days'][$k] = $v->getRowData();
			}
		}
		return $ar;
	}
}

class ForecastDetail extends \chetch\db\DBObject{
	public $forecastID;
	public $key;
	
	public function __construct($rowdata){
		parent::__construct($rowdata);
	}

	protected function assignRowData($rowdata){
		parent::assignRowData($rowdata);
	
		$this->assignR2V($this->forecastID, 'forecast_id');
	}
	
	public function setForecastID($forecastID){
		$this->forecastID = $forecastID;
		$this->set('forecast_id', $this->forecastID);
	}
}

class ForecastHour extends ForecastDetail{
	public static $config = array();
	
	public $forecastDate;
	public $forecastTime;
	
	static public function initialise(){
		$t = Config::get('FORECAST_HOURS_TABLE', 'sf_forecast_hours');
		static::setConfig('TABLE_NAME', $t);
		static::setConfig('SELECT_SQL', "SELECT * FROM $t"); 
		static::setConfig('SELECT_DEFAULT_FILTER', "forecast_id=:forecast_id");
	}
	
	public function __construct($rowdata){
		parent::__construct($rowdata);
	}

	protected function assignRowData($rowdata){
		parent::assignRowData($rowdata);

		$this->assignR2V($this->forecastDate, 'forecast_date');
		$this->assignR2V($this->forecastTime, 'forecast_time');
		
		$this->key = $this->forecastDate.' '.$this->forecastTime;
	}
}

class ForecastDay extends ForecastDetail{
	public static $config = array();
	
	public $forecastDate;
	
	static public function initialise(){
		$t = Config::get('FORECAST_DAYS_TABLE', 'sf_forecast_days');
		static::setConfig('TABLE_NAME', $t);
		static::setConfig('SELECT_SQL', "SELECT * FROM $t"); 
		static::setConfig('SELECT_DEFAULT_FILTER', "forecast_id=:forecast_id");
	}
	
	public function __construct($rowdata){
		parent::__construct($rowdata);
	}

	protected function assignRowData($rowdata){
		parent::assignRowData($rowdata);

		$this->assignR2V($this->forecastDate, 'forecast_date');
		
		$this->key = $this->forecastDate;
	}
}
?>