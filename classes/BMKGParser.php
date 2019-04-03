<?php
class BMKGParser extends Parser{
	
	/*
	 * NOTE: these values are scientific .. the exponent can be found at the end.
	 * hs: wave height (m)
	 * t01: wave mean period (m)
	 * phs00: wind sea (m) É maybe ÔhsÕ refers to wave height (p refers to?)
 	 * phs01: primary swell height (m)
	 * phs02: secondary swell height ?
 	 * ptp00: wind sea period 
 	 * ptp01: primary swell period
 	 * ptp02: secondary swell period?
	 * pdi00: ?
	 * pdi01: ?
	 * pdi02: ?
	 * uwnddeg: ? ... seems to be the same as uwnd although uwnd has values over land
	 * vwnddeg: ? .... same as above
	 * uwnd: x-axis value for wind vector 
	 * vwnd: y-axis value for wind vector
	 * diru: x-axis value for wave vector 
	 * dirv: y-axis value for wave vector
	 */
	
	public function parse($result){
		if($result->response == 'No such file or directory'){
			return false;
		}
		$data = parent::parse($result);
		
		if(!isset($data['data']))throw new Exception("BMKGParser no data parameter found in data");
		$data = $data['data'];
		
		if(!isset($data['datetime']))throw new Exception("BMKGParser no datetime parameter found in data");
		$datetime = $data['datetime'];
		
		//create forecast first
		$forecast = array();
		$forecast['forecast_from'] = $this->getForecastDateAndTime($datetime[0], null, true, false);
		$forecast['forecast_to'] = $this->getForecastDateAndTime($datetime[count($datetime) - 1], null, true, false);
		$forecast['days'] = array();
		$forecast['hours'] = array();
		
		for($i = 0; $i < count($datetime); $i++){
			$row = array();
			$this->assignForecastDateAndTime($row, $datetime[$i]);
			
			$row['swell_height'] = $this->convertNumber($data['hs'][$i], 2, 0, 100);
			$row['swell_height_primary'] =  $this->convertNumber($data['phs01'][$i], 2, 0, 100);
			$row['swell_height_secondary'] =  $this->convertNumber($data['phs02'][$i], 2, 0, 100);
			$row['swell_period'] = $this->convertNumber($data['t01'][$i], 2, 0, 100);
			$row['swell_period_primary'] =  $this->convertNumber($data['ptp01'][$i], 2, 0, 100);
			$row['swell_period_secondary'] =  $this->convertNumber($data['ptp02'][$i], 2, 0, 100);
			
			//we need a bit of trig and pythag to get a direction and speed
			$x = $this->convertNumber($data['diru'][$i]);
			$y = $this->convertNumber($data['dirv'][$i]);
			$row['swell_direction'] = round($this->compass($x, $y), 2);
			
			//we need a bit of trig and pythag to get a direction and speed
			$x = $this->convertNumber($data['uwnd'][$i]);
			$y = $this->convertNumber($data['vwnd'][$i]);
			$row['wind_direction'] = round($this->compass($x, $y), 2);
			$row['wind_speed'] = 1.852*round(sqrt(($x*$x) + ($y*$y)), 2); //convert to KPH
		
			$key = $row['forecast_date'].' '.$row['forecast_time'];
			$forecast['hours'][$key] = $row;
		}
		
		
		return $forecast;
	}
	
	private function convertNumber($val, $round = -1, $min = null, $max = null, $default = null){  //scientific exponent
		//e.g. 0.1908054709434509E+1
		$val2return = null;
		$pos = stripos($val, 'E');
		if($pos !== false){
			$n = substr($val, 0, $pos);
			$x = substr($val, $pos);
			$ar = null;
			if(stripos($x,'+') !== false){
				$ar = explode('+', $x);
			} elseif(stripos($x,'-') !== false){
				$ar = explode('-', $x);
				if(count($ar) == 2)$ar[1] = -$ar[1];
			}
			if(!$ar || count($ar) != 2)throw new Exception("Cannot parse exponent $x");
			$val2return = $n * pow(10, $ar[1]);
		} else {
			$val2return = $val;
		}
		if($round > 0 && $val2return != null)$val2return = round($val2return, $round);
		if($min !== null && $val2return < $min)$val2return = $default;
		if($max !== null && $val2return > $max)$val2return = $default;
		return $val2return;
	}
	
	private function compass($x, $y){
		if($x==0 AND $y==0){ return 0; } // ...or return 360
	    return ($x < 0)
	    ? rad2deg(atan2($x,$y))+360      // TRANSPOSED !! y,x params
	    : rad2deg(atan2($x,$y)); 
	}
}
?>