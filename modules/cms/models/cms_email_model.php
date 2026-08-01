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

	/**
	 * Send or enqueue an email.
	 *
	 * By default the message is written to cache/email_queue/ and sent by the
	 * cms/cms_email_queue cron task. Pass $params['send_now'] => true for
	 * immediate SMTP/mail() (password reminder, verification, password updated).
	 *
	 * @return bool true when sent or enqueued; false on hard failure (missing fields, enqueue I/O error, or send_now transport failure)
	 */
	function send_mail($to, $subject, $body, $params = []){

		if (empty($to) || empty($subject)){
			error_log('cms_email_model send_mail: missing recipient or subject');
			return false;
		}

		if (!empty($params['send_now'])){
			return $this->_deliver_mail($to, $subject, $body, $params);
		}

		return $this->_enqueue_mail($to, $subject, $body, $params);

	}

	/**
	 * Process pending queue files. Called by cms/cms_email_queue cron panel.
	 *
	 * @return array{message?: string, processed: int, sent: int, failed: int, skipped: int, abandoned: int}
	 */
	function process_mail_queue(){

		$dir = $this->_queue_dir();
		$limit = $this->_queue_limit();
		$max_attempts = $this->_queue_max_attempts();
		$now = time();

		$stats = [
			'processed' => 0,
			'sent' => 0,
			'failed' => 0,
			'skipped' => 0,
			'abandoned' => 0,
		];

		if (!is_dir($dir)){
			return $stats;
		}

		$files = glob($dir.DIRECTORY_SEPARATOR.'*.json');
		if (empty($files)){
			return $stats;
		}

		// Oldest first
		usort($files, function($a, $b){
			return filemtime($a) - filemtime($b);
		});

		foreach($files as $path){

			if ($stats['processed'] >= $limit){
				break;
			}

			$raw = @file_get_contents($path);
			if ($raw === false || $raw === ''){
				$stats['skipped']++;
				continue;
			}

			$item = json_decode($raw, true);
			if (!is_array($item) || empty($item['to']) || empty($item['subject'])){
				error_log('cms_email_model process_mail_queue: invalid queue file '.$path);
				@unlink($path);
				$stats['abandoned']++;
				continue;
			}

			$attempts = (int)($item['attempts'] ?? 0);
			$last_try = (int)($item['last_try'] ?? 0);

			if ($attempts >= $max_attempts){
				error_log('cms_email_model process_mail_queue: abandoning after '.$attempts.
						' attempts to '.($item['to'] ?? '').' — '.($item['subject'] ?? '').
						' — last_error: '.($item['last_error'] ?? ''));
				@unlink($path);
				$stats['abandoned']++;
				continue;
			}

			if (!$this->_queue_item_is_due($attempts, $last_try, $now)){
				$stats['skipped']++;
				continue;
			}

			$stats['processed']++;

			// Claim: bump attempts + last_try before send so concurrent cron cannot double-send
			$item['attempts'] = $attempts + 1;
			$item['last_try'] = $now;
			$item['last_error'] = '';
			if (!$this->_write_queue_file($path, $item)){
				error_log('cms_email_model process_mail_queue: failed to claim '.$path);
				$stats['failed']++;
				continue;
			}

			$params = is_array($item['params'] ?? null) ? $item['params'] : [];
			// Never re-enqueue from the worker
			unset($params['send_now']);

			$ok = $this->_deliver_mail(
					$item['to'],
					$item['subject'],
					(string)($item['body'] ?? ''),
					$params
			);

			if ($ok){
				@unlink($path);
				$stats['sent']++;
				continue;
			}

			$item['last_error'] = 'transport failed';
			$this->_write_queue_file($path, $item);
			$stats['failed']++;

			if ($item['attempts'] >= $max_attempts){
				error_log('cms_email_model process_mail_queue: max attempts reached for '.
						$item['to'].' — '.$item['subject']);
				@unlink($path);
				$stats['abandoned']++;
			}

		}

		$stats['message'] = 'email_queue sent='.$stats['sent'].
				' failed='.$stats['failed'].
				' skipped='.$stats['skipped'].
				' abandoned='.$stats['abandoned'].
				' processed='.$stats['processed'];

		return $stats;

	}

	/**
	 * Backoff: first try immediate; after n failures wait 2*n² minutes.
	 * n = attempts already made (0 = never tried).
	 */
	function _queue_item_is_due($attempts, $last_try, $now = null){

		if ($now === null){
			$now = time();
		}

		$attempts = (int)$attempts;
		$last_try = (int)$last_try;

		if ($attempts <= 0 || $last_try <= 0){
			return true;
		}

		// Wait 2 * n² minutes after the nth failure (n = attempts)
		$wait_seconds = 2 * $attempts * $attempts * 60;

		return ($now - $last_try) >= $wait_seconds;

	}

	function _enqueue_mail($to, $subject, $body, $params){

		$dir = $this->_queue_dir();
		if (!is_dir($dir)){
			if (!@mkdir($dir, 0755, true) && !is_dir($dir)){
				error_log('cms_email_model send_mail: cannot create queue dir '.$dir);
				return false;
			}
		}

		// Strip send_now if present; worker always delivers
		$queue_params = $params;
		unset($queue_params['send_now']);

		$id = date('YmdHis').'_'.bin2hex(random_bytes(8));
		$path = $dir.DIRECTORY_SEPARATOR.$id.'.json';

		$item = [
			'id' => $id,
			'to' => $to,
			'subject' => $subject,
			'body' => $body,
			'params' => $queue_params,
			'created' => time(),
			'attempts' => 0,
			'last_try' => 0,
			'last_error' => '',
		];

		if (!$this->_write_queue_file($path, $item)){
			error_log('cms_email_model send_mail: failed to write queue file for '.$to.' — '.$subject);
			return false;
		}

		return true;

	}

	function _write_queue_file($path, $item){

		$json = json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false){
			return false;
		}

		return @file_put_contents($path, $json, LOCK_EX) !== false;

	}

	function _queue_dir(){

		return $GLOBALS['config']['base_path'].'cache/email_queue';

	}

	function _queue_limit(){

		$n = (int)($GLOBALS['config']['email_queue_limit'] ?? 50);
		if ($n < 1){
			$n = 50;
		}

		return $n;

	}

	function _queue_max_attempts(){

		$n = (int)($GLOBALS['config']['email_queue_max_attempts'] ?? 5);
		if ($n < 1){
			$n = 5;
		}

		return $n;

	}

	/**
	 * Immediate transport (SMTP or PHP mail).
	 */
	function _deliver_mail($to, $subject, $body, $params){

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
