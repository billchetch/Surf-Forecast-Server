<?php
//require_once('_include.php');

use \chetch\Config as Config;

$trace = false;

function download($url, $payload, $encoding, $headers){
	//retrieve data
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_HEADER, false); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	if($encoding)curl_setopt($ch, CURLOPT_ENCODING, $this->get('encoding'));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, Config::get('CURLOPT_CONNECTTIMEOUT',30));
	curl_setopt($ch, CURLOPT_TIMEOUT, Config::get('CURLOPT_TIMEOUT',30));
		
	if(!empty($payload)){
		curl_setopt($ch, CURLOPT_POST, 1);
		//echo $this->payload; die;
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	}
		
	$data = curl_exec($ch); 
	$error = curl_error($ch);
	$errno = curl_errno($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
				
    //store stuff if it's any good
    if($data && $errno == 0 && $info['http_code'] < 400)
	{
        return $data;
    } else {
        throw new Exception($error ? $errno.': '.$error : 'http code: '.$info['http_code']);
    }
}

try{
	$lf = "\n";
	$source = $_GET['source'];

	//At the moment the only source is surfline so we don't check source (this an come later)'
	$spotId = $_GET['spot_id']; //"640a69004eb375bdb39e4cb3";
	//$spotId = "640a69004eb375bdb39e4cb3";
	
	$forecasts = array();
	//note: don't change the order of these ... wave must come first!'
	$forecasts['wave'] = array('qs'=>"spotId=$spotId&days=5&intervalHours=1&cacheEnabled=true&units%5BswellHeight%5D=M&units%5BwaveHeight%5D=M");
	$forecasts['wind'] = array('qs'=>"spotId=$spotId&days=5&intervalHours=1&corrected=false&cacheEnabled=true&units%5BwindSpeed%5D=KPH");
	$forecasts['rating'] = array('qs'=>"spotId=$spotId&days=5&intervalHours=1&cacheEnabled=true");
	
	$baseurl = "https://services.surfline.com/kbyg/spots/forecasts/";
	$headers = array();
	$headers[] = "Origin: https://www.surfline.com";
	$headers[] = "Referer: https://www.surfline.com/";
	//$headers[] = 'Sec-Ch-Ua: "Not.A/Brand";v="8", "Chromium";v="114", "Google Chrome";v="114"';
	//$headers[] = 'Sec-Ch-Ua-Mobile: ?0';
	//$headers[] = 'Sec-Ch-Ua-Platform: "macOS"';
	$headers[] = "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36";

	$rows = array();
	$fromTimestamp = 0;
	$toTimestamp = 0;
	foreach($forecasts as $k=>$f){
		$url = $baseurl.$k.'?'.$f['qs'];

		if($trace)echo "Starting download of $k: $url $lf";
		$s = download($url, null, null, $headers);
		$data = json_decode($s, true);
		if(json_last_error()){
			throw new Exception("JSON error: ".json_last_error());
		}
		if($trace)echo "Download $k successful $lf";
		sleep(1);

		//all teh data from the feed we want to add
		$data2add = $data['data'][$k];
		foreach($data2add as $d){
			//create the row we want to fill
			$ts = $d['timestamp'];
			$tskey = "T$ts";
			if(!isset($rows[$tskey])){
				if($k == 'wave'){
					$rows[$tskey] = array('timestamp'=>$ts, 'date_time'=>date('Y-m-d H:i:s', $ts)); //this is UTC
				} else {
					if($trace)echo "Cannot find timestamp key $ts in array when processing $k so skipping $lf ";
					continue;
				}
			}
			if($fromTimestamp == 0 || $ts < $fromTimestamp)$fromTimestamp = $ts;
			if($ts > $toTimestamp)$toTimestamp = $ts;
			
			//now we do individual parsing
			$row = array();
			switch($k){
				case 'wave':
					$surf = $d['surf'];
					$swells = $d['swells'];
					$dkey = 'height'; //in meters
					$primarySwell = null;
					$secondarySwell = null;
					foreach($swells as $swell){
						if(!$primarySwell && $swell['height'] > 0){
							$primarySwell = $swell;
						} elseif($primarySwell && !$secondarySwell && $swell['height'] > 0){
							$secondarySwell = $swell;
						}
					}

					$min = $surf['min'];
					$max = min($surf['max'], $min + 1);
					$row['swell_height'] = ($max + $min) / 2;
					$row['swell_height_primary'] = $primarySwell ? $primarySwell[$dkey] : null;
					$row['swell_height_secondary'] = $secondarySwell ? $secondarySwell[$dkey] : null;

					$dkey = 'period'; //in seconds
					$row['swell_period'] = $primarySwell ? $primarySwell[$dkey] : null;
					$row['swell_period_primary'] = $primarySwell ? $primarySwell[$dkey] : null;
					$row['swell_period_secondary'] = $secondarySwell ? $secondarySwell[$dkey] : null;
					
					$dkey = 'direction'; //in degrees
					$row['swell_direction'] = $primarySwell ? $primarySwell[$dkey] : null;
					$row['swell_direction_primary'] = $primarySwell ? $primarySwell[$dkey] : null;
					$row['swell_direction_secondary'] = $secondarySwell ? $secondarySwell[$dkey] : null;
					break;

				case 'wind':
					$row['wind_speed'] = $d['speed']; //in kph
					$row['direction'] = $d['direction']; //in degrees
					break;

				case 'rating':
					$rating = $d['rating'];
					$row['rating'] = $rating['value'];
					break;
			}

			//assign this row to the row by timestamp
			foreach($row as $rk=>$rv){
				$rows[$tskey][$rk] = $rv;
			}
		} //end adding data from a particular url
	} //end looping throw urls

	//here are rows are complete
	$result = array();
	$result['period'] = array('from'=>$fromTimestamp, 'to'=>$toTimestamp, 'from_date_time'=>date('Y-m-d H:i:s', $fromTimestamp), 'to_date_time'=>date('Y-m-d H:i:s', $toTimestamp));
	$result['data'] = $rows;
	$s = json_encode($result);
	echo $s;

} catch (Exception $e){
	/*$error = array();
	$error['error'] = $e->getMessage();
	echo json_encode($error);*/
	var_dump($e);
}
?>