<?php

class ProxyFeedParser extends Parser{
	public function parse($result){ //result is an instance of FeedResult
		
		$data = parent::parse($result);
		if(!empty($data['error_response'])){
			$errMsg = !empty($data['error_response']['error_msg']) ? $data['error_response']['error_msg'] : "Error response data found in donwloaded feed";
			throw new Exception($errMsg);
		}

		$forecast = array();
		$tz = $result->get('timezone');
		$forecast['forecast_from'] = $this->getForecastDateAndTime($data['period']['from'], $tz, true, false);
		$forecast['forecast_to'] = $this->getForecastDateAndTime($data['period']['to'], $tz, true, false);

		$forecast['hours'] = array();
		$forecast['days'] = array();

		foreach($data['data'] as $k=>$r){
			$row = $r;
			$this->assignForecastDateAndTime($row, $r['timestamp'], $tz);
			$key = $row['forecast_date'].' '.$row['forecast_time'];
			$forecast['hours'][$key] = $row;
		}

		return $forecast;
	}
}
?>