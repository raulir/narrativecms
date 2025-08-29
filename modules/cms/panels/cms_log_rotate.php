<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists($GLOBALS['config']['base_path'] . 'vendor/autoload.php')){
	require_once($GLOBALS['config']['base_path'] . 'vendor/autoload.php');
}

require_once('system/vendor/phpmailer/Exception.php');
require_once('system/vendor/phpmailer/PHPMailer.php');
require_once('system/vendor/phpmailer/SMTP.php');

class cms_log_rotate extends CI_Controller {

	function panel_action(){
		
		if (empty($GLOBALS['config']['errors_log'])){
			return;
		}
		
		$explode_str = '] ';
		$trim_str = '[';
				
		$filename = $GLOBALS['config']['errors_log'];
		
		$lines = file($filename);
		
		$errors = array();
		
		foreach($lines as $line){
		
			list($time, $error_text) = explode($explode_str, $line);
		
			$error_found = 0;
			foreach($errors as $key => $error) {
				if ($error['message'] == $error_text){
					$errors[$key]['count'] += 1;
					$errors[$key]['times'][] = trim($time, $trim_str);
					$error_found = 1;
				}
			}
		
			if ($error_found == 0){
				$errors[] = array(
						'message' => $error_text,
						'count' => 1,
						'times' => array(trim($time, $trim_str)),
				);
			}
		
		}
		
		// sort errors by count
		
		function mysort($a, $b){
			if ($a['count'] > $b['count']){
				return -1;
			}
			if ($a['count'] < $b['count']){
				return 1;
			}
			return 0;
		}
		
		usort($errors, 'mysort');
		
		// put together mail text
		
		$email_filename = str_replace($GLOBALS['config']['base_path'], '', $filename);
		
		$text = '';
		
		$text .= '<b>PHP errors report:</b>'."\n\n";
		$text .= 'Server name: '.strtolower($_SERVER['SERVER_NAME'])."\n";
		$text .= 'Errors from: '.$email_filename."\n\n";
		$text .= 'Count - Last seen - Error'."\n";
		foreach($errors as $error) {
			$text .= sprintf('%7s', $error['count']).' - '.$error['times'][(count($error['times']) - 1)].' - '.$error['message'];
		}

		// add stats to archive too
		file_put_contents($GLOBALS['config']['base_path'].'cache/php_errors_'.date('Y-m-d_H-i-s').'.log', $text);

		// empty logfile
		file_put_contents($filename, '');
		
		if (!empty($GLOBALS['config']['admin_email'])){
			
			if(empty($GLOBALS['config']['smtp_server'])){
				 
				// send email
				@mail($GLOBALS['config']['admin_email'], 'PHP errors from '.$email_filename, $text,
						'From: '.$GLOBALS['config']['from_name'].'<'.$GLOBALS['config']['email'].'>'."\r\n".
						'Reply-to: '.$GLOBALS['config']['reply_name'].'<'.$GLOBALS['config']['reply_email'].'>'."\r\n");
				 
			} else {
			
				$text = str_replace(["\n", "\r\n"], '<br>', $text);
				 
				$from = $GLOBALS['config']['email'];
			
				$mail = new PHPMailer(true);
			
				$mail->isSMTP();
				$mail->Host = $GLOBALS['config']['smtp_server'];
				$mail->SMTPAuth = true;
				$mail->Username = $GLOBALS['config']['smtp_username'];
				$mail->Password = $GLOBALS['config']['smtp_password'];
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
				$mail->Port = $GLOBALS['config']['smtp_port'];
				 
				$mail->setFrom($GLOBALS['config']['email'], strtolower($_SERVER['SERVER_NAME']));
				$mail->addAddress($GLOBALS['config']['admin_email']);
				 
				$mail->CharSet = 'utf-8';
			
				$mail->addCustomHeader('Auto-Submitted', 'auto-generated');
			
				$mail->Subject = 'PHP errors '.strtolower($_SERVER['SERVER_NAME']);
				$mail->Body = '<html><body>'.$text.'</body></html>';
				$mail->IsHTML(true); // $autoreply_html);
				 
				$mail->send();
				 
			}

		}
	
	}

}
