<?php

namespace emailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists($GLOBALS['config']['base_path'] . 'vendor/autoload.php')){
	require_once($GLOBALS['config']['base_path'] . 'vendor/autoload.php');
}

require_once('system/vendor/phpmailer/Exception.php');
require_once('system/vendor/phpmailer/PHPMailer.php');
require_once('system/vendor/phpmailer/SMTP.php');

if (! defined ( 'BASEPATH' )) exit ( 'No direct script access allowed' );

class send extends \Controller {

	function panel_params($params) {
		
		if ($params['do'] == 'send'){
		
			set_time_limit(300);
			
			$emails = preg_split('/[\s,]+/', $params['to_addresses']);
			$emails = array_map('trim', $emails);
			$emails = array_filter($emails, fn($e) => $e !== '');
			$emails = array_unique($emails);
			
			$valid_emails = [];
			foreach ($emails as $email) {
	        	if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
	            	$valid_emails[] = $email;
	        	}
		    }
					
			$mail = new PHPMailer(true);
			
			$mail->isSMTP();
			$mail->Host = $GLOBALS['config']['smtp_server'];
			$mail->SMTPAuth = true;
			$mail->Username = $GLOBALS['config']['smtp_username'];
			$mail->Password = $GLOBALS['config']['smtp_password'];
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			$mail->Port = $GLOBALS['config']['smtp_port'];
			
			$mail->setFrom($params['from_address'], $params['from_name']);
	
			$mail->CharSet = 'utf-8';
			$mail->addCustomHeader ( 'Auto-Submitted', 'auto-generated' );
			$mail->Subject = $params['subject'];
			$mail->Body = $params['body'];
			$mail->IsHTML(true);
	
			$mail->XMailer = 'Rauli CMS';
			
			$params['n'] = 0;
			foreach ($valid_emails as $to_email) {
				
				$mail->clearAddresses();
				$mail->addAddress($to_email);
				$mail->send();
				
				$params['n'] += 1;
				
				sleep(1);
	
			}
			
		}
		
		return $params;
		
	}
	
}
