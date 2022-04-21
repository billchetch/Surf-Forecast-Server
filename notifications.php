<?php
require('_include.php');


use chetch\Config as Config;
use chetch\Utils as Utils;
use chetch\sys\Logger as Logger;
use chetch\sys\SysInfo as SysInfo;

$log = Logger::getLog('notifications', Logger::LOG_TO_SCREEN);

try{
	$digests = array();
	$rdigests = Digest::getReceived();
	$odigests = Digest::getOutstanding();
	$log->info(count($rdigests)." digests received, ".count($odigests)." digests outstanding");
	foreach($rdigests as $d)array_push($digests, $d);
	foreach($odigests as $d)array_push($digests, $d);
	
	if(count($digests) == 0){
		$log->finish();
		die;
	}

	$body = '';
	$lf = "\n";
	$digests2mail = array();
	$max = min(count($digests), Config::get('MAX_NOTIFICATIONS_TO_SEND', 8));
	$log->info("Preparing $max digests to send");
	for($i = 0; $i < $max; $i++){
		$dg = $digests[$i];
		$title = $dg->get('digest_title');
		$dt = "";
		if($dg->get('source_created')){
			$dt = $dg->get('source_created').' '.$dg->get('source_timezone_offset');
		} else {
			$dt = $dg->get('created').' '.$dg->tzoffset();
		} 
		
		$source = $dg->get('source') ? $dg->get('source') : 'local'; 
		$dgs = "$dt ($source): ".$title.$lf.$lf.$dg->get('digest');
		if(!isset($digests2mail[$title])){
			$digests2mail[$title] = array('body'=>$dgs, 'digests'=>array());
		} else {
			$digests2mail[$title]['body'].= $lf.$lf.$dgs;
		}
		array_push($digests2mail[$title]['digests'], $dg);
	}
	
	$to = Config::get('EMAIL_DIGESTS_TO', 'bill@bulan-baru.com');
	$mail = getMailer($to, "", "", "sf@bulan-baru.com", "sf@bulan-baru.com");
		
	$log->info("Attempting to send ".count($digests2mail)." emails");
	foreach($digests2mail as $subject=>$data){
		$mail->Subject = "BBSF: ".$subject;
		$mail->Body = $data['body']; 
		$log->info("Trying to send email ".$mail->Subject." to $to on ".$mail->Host.":".$mail->Port." using security ".$mail->SMTPSecure);
		if($mail->Send()){
			$log->info("Emailed subject $subject to $to");
			$log->info("Updating status of ".count($data['digests']));

			foreach($data['digests'] as $dg){
				switch($dg->get('status')){
					case Digest::STATUS_RECEIVED:
						$newStatus = Digest::STATUS_RECEIVED_AND_EMAILED;
						break;
					case Digest::STATUS_OUTSTANDING:
						$newStatus = Digest::STATUS_EMAILED;
						break;

					default:
						throw new Exception("Unrecognised digest status of ".$dg->get('status'));
						break;
				}
				$dg->setStatus($newStatus);
				$dg->write();
			} 
		} else {
			$log->warning("Could not email subject $subject to $to");
		}
		//echo "Email: $to\nSubject: $subject\nBody:$body\n";
	}

	$log->finish();

} catch (Exception $e){
	$log->exception($e->getMessage());
}