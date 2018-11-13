<?php
class WorldTidesParser extends Parser{
	public function parse($result){
		$data = parent::parse($result);
		$heights = $data['heights'];
		$extremes = $data['extremes'];
		
		$forecast = array();
		$forecast['forecast_from'] = $this->getForecastDateAndTime($heights[0]['dt'], $result->timezone, true, false);
		$forecast['forecast_to'] = $this->getForecastDateAndTime($heights[count($heights) - 1]['dt'], $result->timezone, true, false);
		
		//tide extremes
		$i = 0;
		$row = null;
		foreach($extremes as $r){
			$dt = $this->getForecastDateAndTime($r['date'], $result->timezone, false, true);
			$date = $dt['date'];
			$key = $date;
			if(!isset($forecast['days'][$key])){
				$i = 1;
				$row = array();
				$row['forecast_date'] = $date;
			}
			
			$row['tide_extreme_type_'.$i] = strtoupper($r['type']);
			$row['tide_extreme_height_'.$i] = $r['height'];
			$row['tide_extreme_time_'.$i] = $dt['time'];
			$i++;
			
			$forecast['days'][$key] = $row;
		}
		
		//tide heights and times
		$forecast['hours'] = array();
		foreach($heights as $r){
			$row = array();
			$this->assignForecastDateAndTime($row, $r['dt'], $result->timezone);
			$row['tide_height'] = $r['height'];
			
			$date = $row['forecast_date'];
			$key = $date.' '.$row['forecast_time'];
			$forecast['hours'][$key] = $row;
		}
		
		
		return $forecast;
	}
}
?>