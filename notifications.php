<?php
require('_include.php');

Logger::init($dbh, array('log_name'=>'notifications', 'log_options'=>Logger::LOG_TO_SCREEN));

try{
	Digest::init($dbh);
	$digests = array();
	$rdigests = Digest::getReceived();
	$odigests = Digest::getOutstanding();
	Logger::info(count($rdigests)." digests received, ".count($odigests)." digests outstanding");
	foreach($rdigests as $d)array_push($digests, $d);
	foreach($odigests as $d)array_push($digests, $d);
	
	$body = '';
	$lf = "\n";
	$digests2mail = array();
	
	
	foreach($digests as $dg){
		$title = $dg->rowdata['digest_title'];
		$dt = "";
		if($dg->rowdata['source_created']){
			$dt = $dg->rowdata['source_created'].' '.$dg->rowdata['source_timezone_offset'];
		} else {
			$dt = $dg->rowdata['created'].' '.$dg->tzoffset();
		} 
		
		$source = $dg->rowdata['source'] ? $dg->rowdata['source'] : 'local'; 
		$dgs = "$dt ($source): ".$title.$lf.$lf.$dg->rowdata['digest'];
		if(!isset($digests2mail[$title])){
			$digests2mail[$title] = array('body'=>$dgs, 'digests'=>array());
		} else {
			$digests2mail[$title]['body'].= $lf.$lf.$dgs;
		}
		array_push($digests2mail[$title]['digests'], $dg);
	}
	
	$to = Config::get('EMAIL_DIGESTS_TO', 'bill@bulan-baru.com');
	$mail = getMailer($to, "", "", "sf@bulan-baru.com", "sf@bulan-baru.com");
		
	foreach($digests2mail as $subject=>$data){
		$mail->Subject = "BBSF: ".$subject;
		$mail->Body = $data['body']; 
		if($mail->Send()){
			Logger::info("Emailed subject $subject to $to");
			foreach($data['digests'] as $dg){
				switch($dg->rowdata['status']){
					case Digest::STATUS_RECEIVED:
						$newStatus = Digest::STATUS_RECEIVED_AND_EMAILED;
						break;
					case Digest::STATUS_OUTSTANDING:
						$newStatus = Digest::STATUS_EMAILED;
						break;
				}
				$dg->setStatus($newStatus);
				$dg->write();
			} 
		} else {
			Logger::warning("Could not email subject $subject to $to");
		}
		//echo "Email: $to\nSubject: $subject\nBody:$body\n";
	}

} catch (Exception $e){
	Logger::exception($e->getMessage());
}