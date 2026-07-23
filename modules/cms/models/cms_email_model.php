<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if (file_exists($GLOBALS['config']['base_path'].'vendor/autoload.php')){
	require_once($GLOBALS['config']['base_path'].'vendor/autoload.php');
}

require_once('system/vendor/phpmailer/Exception.php');
require_once('system/vendor/phpmailer/PHPMailer.php');
require_once('system/vendor/phpmailer/SMTP.php');

class cms_email_model extends \Model {

	function send_mail($to, $subject, $body, $params = []){

		if (empty($to) || empty($subject)){
			error_log('cms_email_model send_mail: missing recipient or subject');
			return false;
		}

		if ($this->_smtp_configured()){
			return $this->_send_via_smtp($to, $subject, $body, $params);
		}

		return $this->_send_via_php_mail($to, $subject, $body, $params);

	}

	function _smtp_configured(){

		return !empty($GLOBALS['config']['smtp_server']);

	}

	function _get_from_email($params){

		$from = trim((string)($params['from_email'] ?? $GLOBALS['config']['email'] ?? ''));

		if ($from === ''){
			$host = $_SERVER['SERVER_NAME'] ?? 'localhost';
			$from = 'noreply@'.$host;
		}

		return $from;

	}

	function _get_from_name($params){

		$name = trim((string)($params['from_name'] ?? $GLOBALS['config']['from_name'] ?? ''));

		if ($name === ''){
			$name = strtolower($_SERVER['SERVER_NAME'] ?? 'localhost');
		}

		return $name;

	}

	function _get_reply_to($params){

		$from_email = $this->_get_from_email($params);

		return [
			'email' => trim((string)($params['reply_to']['email'] ?? $GLOBALS['config']['reply_email'] ?? $from_email)),
			'name' => (string)($params['reply_to']['name'] ?? $GLOBALS['config']['reply_name'] ?? ''),
		];

	}

	function _configure_phpmailer($mail, $params){

		$mail->isSMTP();
		$mail->Host = $GLOBALS['config']['smtp_server'];
		$mail->SMTPAuth = true;
		$mail->Username = $GLOBALS['config']['smtp_username'];
		$mail->Password = $GLOBALS['config']['smtp_password'];
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		$mail->Port = $GLOBALS['config']['smtp_port'];
		$mail->CharSet = 'utf-8';

		$smtp_debug = !empty($params['smtp_debug']) || !empty($GLOBALS['config']['smtp_debug']);

		if ($smtp_debug){
			$GLOBALS['smtp_debug'] = [];
			$mail->SMTPDebug = 2;
			$mail->Debugoutput = function($line, $level){
				$GLOBALS['smtp_debug'][] = $line;
			};
		}

		if (!empty($params['x_mailer'])){
			$mail->XMailer = $params['x_mailer'];
		}

	}

	function _send_via_smtp($to, $subject, $body, $params){

		try {

			$mail = new PHPMailer(true);

			$this->_configure_phpmailer($mail, $params);

			$mail->setFrom($this->_get_from_email($params), $this->_get_from_name($params));
			$mail->addAddress($to);

			$reply_to = $this->_get_reply_to($params);
			$mail->addReplyTo($reply_to['email'], $reply_to['name']);

			if (!empty($params['auto_submitted'])){
				$mail->addCustomHeader('Auto-Submitted', 'auto-generated');
			}

			$is_html = !empty($params['is_html']);

			$mail->Subject = $subject;
			$mail->Body = $body;
			$mail->IsHTML($is_html);

			if ($is_html && !empty($params['alt_body'])){
				$mail->AltBody = $params['alt_body'];
			}

			$sent = $mail->send();

			if (isset($GLOBALS['smtp_debug'])){
				$debug_output = implode("\r\n", $GLOBALS['smtp_debug']);
				file_put_contents(
					$GLOBALS['config']['base_path'].'cache/smtp_debug_'.$GLOBALS['config']['smtp_server'].'_'.time().'.txt',
					$debug_output
				);
				unset($GLOBALS['smtp_debug']);
			}

			return $sent;

		} catch (\Exception $e) {
			error_log('cms_email_model send_mail: SMTP send failed to '.$to.' — '.$subject.' — '.$e->getMessage());
			return false;
		}

	}

	function _send_via_php_mail($to, $subject, $body, $params){

		$from_email = $this->_get_from_email($params);
		$from_name = $this->_get_from_name($params);
		$reply_to = $this->_get_reply_to($params);

		$mail_body = $body;

		if (!empty($params['is_html']) && !empty($params['alt_body'])){
			$mail_body = $params['alt_body'];
		}

		$headers = [];

		if (!empty($params['mail_from_email_only'])){
			$headers[] = 'From: '.$from_email;
		} else {
			$headers[] = 'From: '.$from_name.'<'.$from_email.'>';
			if ($reply_to['email'] !== ''){
				$headers[] = 'Reply-to: '.$reply_to['name'].'<'.$reply_to['email'].'>';
			}
		}

		if (!empty($params['auto_submitted'])){
			$headers[] = 'Auto-Submitted: auto-generated';
		}

		$header = implode("\r\n", $headers)."\r\n";

		$sent = @mail($to, $subject, $mail_body, $header);

		if (!$sent){
			error_log('cms_email_model send_mail: PHP mail() failed to '.$to.' — '.$subject);
		}

		return $sent;

	}

}