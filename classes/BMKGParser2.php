<?php
class BMKGParser2 extends Parser{

	//const DEFAULT_MODEL = "w3g_reg";
	//const DEFAULT_MODEL_VARS = '"wspd","wdir","hs","t01","dir","ptp00","ptp01","phs01","phs00"';
	
	const DEFAULT_MODEL = "w3g_hires";
	const DEFAULT_MODEL_VARS = '"wspd","wdir","hs","t01","dir","ptp00","ptp01","phs01","phs00"';
	
	public static function getDateTime(){
		$date = new DateTime("now", new DateTimeZone("UTC"));
		/*$H = '00';
		$h = (int)$date->format('H');
		if($h < 12){
			$H = '00';
			$date->setTimestamp($now - 24*3600);
		} elseif($h < 18){
			$H = '12';
			$date->setTimestamp($now - 24*3600);
		}*/
		$dt = $date->format('Y-m-d').'T00:00:00Z';
		return $dt;
	}
	
	
public function parse($result){
		if($result->response && stristr($result->response, "The requested URL was not found on the server") !== false){
			return false;
		}
		$data = parent::parse($result);
		if(!isset($data['time']))throw new Exception("BMKGParser2 no time parameter found in data");
		$datetime = $data['time'];
		
		if(empty($result->timezone))throw new Exception("BMKGParser2 no timezone FeedResult class property found");
		
		$convertedDateTime = array();
		$tz = new DateTimeZone($result->timezone);
		for($i = 0; $i < count($datetime); $i++){
			$datestr = $datetime[$i];
			$dt = new DateTime(date('Y-m-d H:i:s', strtotime($datestr)));
			$dt->setTimezone($tz);
			$convertedDateTime[$i] = $dt->format('Y-m-d H:i:s');
		}
		
		$forecast = array();
		$forecast['forecast_from'] = $this->getForecastDateAndTime($convertedDateTime[0], null, true, false);
		$forecast['forecast_to'] = $this->getForecastDateAndTime($datetime[count($convertedDateTime) - 1], null, true, false);
		$forecast['days'] = array();
		$forecast['hours'] = array();
		
		for($i = 0; $i < count($convertedDateTime); $i++){
			$row = array();
			$this->assignForecastDateAndTime($row, $convertedDateTime[$i]);
			
			$row['swell_height'] = $this->extractValue($data, 'hs', '0', $i);
			$row['swell_height_primary'] =  $this->extractValue($data, 'phs01', '0', $i);
			$row['swell_height_secondary'] =  $this->extractValue($data, 'phs02', '0', $i);
			$row['swell_period'] = $this->extractValue($data, 't01', '0', $i);
			$row['swell_period_primary'] =  $this->extractValue($data, 'ptp01', '0', $i);
			$row['swell_period_secondary'] =  $this->extractValue($data, 'ptp02', '0', $i);
			$row['swell_direction'] = $this->extractValue($data, 'dir', '0', $i);
			
			//we need a bit of trig and pythag to get a direction and speed
			$row['wind_direction'] = $this->extractValue($data, 'wdir', '0', $i);
			$row['wind_speed'] = $this->extractValue($data, 'wspd', '0', $i);
		
			$key = $row['forecast_date'].' '.$row['forecast_time'];
			$forecast['hours'][$key] = $row;
		}
		
		return $forecast;
	}
	
	function extractValue($data, $var, $key, $index, $default = null){
		if(empty($data[$var]) || empty($data[$var][$key]) || empty($data[$var][$key][$index]))return $default;
		
		$val = $data[$var][$key][$index];
		switch($var){
			case 'wspd':
				return round(1.852*$val, 2);
				
			default:
				return round($val, 2);
		}
	}
}