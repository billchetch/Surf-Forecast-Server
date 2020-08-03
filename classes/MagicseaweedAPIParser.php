<?php
class MagicseaweedAPIParser extends Parser{
	
	private function conv($unit){
		$conv = 1;
		switch($unit){
			case 'ft': $conv = 0.3048; break; //ft to meters
			case 'mph': $conv = 1.60934; break;
			case 'm': $conv = 1; break;
			case 'kph': $conv = 1; break;	
			default:
				throw new Exception("MagicseaweddAPIParser unknown swell height unit: $unit");
		}
		return $conv;
	}
	
	public function parse($result){ //result is an instance of FeedResult
		$data = parent::parse($result);
		if(!empty($data['error_response'])){
			$errMsg = !empty($data['error_response']['error_msg']) ? $data['error_response']['error_msg'] : "Error response data found in donwloaded feed";
			throw new Exception($errMsg);
		}


		$forecast = array();
		$forecast['forecast_from'] = $this->getForecastDateAndTime($data[0]['localTimestamp'], null, true, false);
		$forecast['forecast_to'] = $this->getForecastDateAndTime($data[count($data) - 1]['localTimestamp'], null, true, false);
		
		$forecast['hours'] = array();
		$forecast['days'] = array();
		foreach($data as $r){
			$row = array();
			$this->assignForecastDateAndTime($row, $r['localTimestamp']);
			
			$swell = $r['swell'];
			$unit = $swell['unit'];
			$conv = $this->conv($unit);
			
			$combined = $swell['components']['combined'];
			$primary = $swell['components']['primary'];
			$secondary = isset($swell['components']['secondary']) ? $swell['components']['secondary'] : null;
			
			$row['swell_height'] = round($combined['height']*$conv, 2);
			$row['swell_height_primary'] =  round($primary['height']*$conv, 2);
			if($secondary)$row['swell_height_secondary'] =  round($secondary['height']*$conv, 2);
			
			$row['swell_period'] = round($combined['period'], 2);
			$row['swell_period_primary'] =  round($primary['period'], 2);
			if($secondary)$row['swell_period_secondary'] =  round($secondary['period'], 2);
			
			//degrees
			$row['swell_direction'] = round($combined['direction'], 2);
			$row['swell_direction_primary'] = round($primary['direction'], 2);
			if($secondary)$row['swell_direction_secondary'] = round($secondary['direction'], 2);
			
			$wind = $r['wind'];
			$unit = $wind['unit'];
			$conv = $this->conv($unit);
			$row['wind_direction'] = round($wind['direction'], 2); 
			$row['wind_speed'] = round($wind['speed']*$conv, 2);
			
			$row['rating'] = $r['solidRating'];
			
			$key = $row['forecast_date'].' '.$row['forecast_time'];
			$forecast['hours'][$key] = $row;
			
		}
		
		return $forecast;
	}
}
?>