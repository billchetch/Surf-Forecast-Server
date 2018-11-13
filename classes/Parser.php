<?php
class Parser{
	public $data;
	
	public function parse($result){
		$dataAsString = $result->response;
		$format = $result->responseFormat;
		
		if($format != 'JSON')throw new Exception("Parser::parse format $format is not JSON");
		$this->data = json_decode($dataAsString, true);
		if(json_last_error()){
			throw new Exception(json_last_error());
		}
		return $this->data;
	}
	
	public static function getForecastDateAndTime($datestr, $timezone = null, $round2hour = true, $asArray = true){
		if(is_numeric($datestr))$datestr = date('Y-m-d H:i:s', $datestr);
		$dt = new DateTime($datestr);
		if($timezone)$dt->setTimezone(new DateTimeZone($timezone));
		$date = $dt->format('Y-m-d');
		$time = null;
		if($round2hour){
			$hour = $dt->format('H');
			$time = str_pad($hour, 2, '0', STR_PAD_LEFT).':00:00';
		} else {
			$time = $dt->format('H:i:s');
		}
		if($asArray){
			return array('date'=>$date, 'time'=>$time);
		} else {
			return $date.' '.$time;
		}
	}
	
	public static function assignForecastDateAndTime(&$vals, $datestr, $timezone = null){
		$dt = self::getForecastDateAndTime($datestr, $timezone);
		$vals['forecast_date'] = $dt['date'];
		$vals['forecast_time'] = $dt['time'];
	}
}
?>