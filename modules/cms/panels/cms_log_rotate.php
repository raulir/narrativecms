<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

class cms_log_rotate extends \Controller {

	/**
	 * Min seconds between emails when the log has no real errors (23h 45m).
	 * 15 minutes headroom so daily cron jitter does not skip a day.
	 */
	function _empty_email_min_interval(){
		return (23 * 3600) + (45 * 60);
	}

	function _last_email_marker_path(){
		return $GLOBALS['config']['base_path'].'cache/php_errors_last_email_at';
	}

	function _get_last_email_time(){
		$path = $this->_last_email_marker_path();
		if (!is_file($path)){
			return 0;
		}
		$raw = trim((string)@file_get_contents($path));
		if ($raw === '' || !ctype_digit($raw)){
			return 0;
		}
		return (int)$raw;
	}

	function _set_last_email_time($time = null){
		if ($time === null){
			$time = time();
		}
		@file_put_contents($this->_last_email_marker_path(), (string)(int)$time);
	}

	function panel_action(){
		
		if (empty($GLOBALS['config']['errors_log'])){
			return;
		}
		
		$explode_str = '] ';
		$trim_str = '[';
				
		$filename = $GLOBALS['config']['errors_log'];

		if (!is_string($filename) || $filename === '' || !is_file($filename)){
			return;
		}
		
		$lines = file($filename);
		if ($lines === false){
			return;
		}
		
		$errors = [];
		$skipped = [];
		
		foreach($lines as $line){
		
			if (!stristr($line, $explode_str)){
				$skipped[] = $line;
				continue;
			}
			
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
		
		// Sort by count descending
		usort($errors, function($a, $b){
			return ((int)$b['count']) <=> ((int)$a['count']);
		});

		$has_errors = !empty($errors);

		// Empty report: email at most once per ~day (23h 45m since last real send)
		if (!$has_errors){
			$last_sent = $this->_get_last_email_time();
			$elapsed = $last_sent > 0 ? (time() - $last_sent) : PHP_INT_MAX;
			if ($elapsed < $this->_empty_email_min_interval()){
				// Still clear the log so noise does not pile up
				file_put_contents($filename, '');
				return [
						'message' => 'PHP error log empty — email skipped (last sent '.
								($last_sent ? date('Y-m-d H:i:s', $last_sent) : 'never').')',
				];
			}
		}
		
		// put together mail text
		
		$email_filename = str_replace($GLOBALS['config']['base_path'], '', $filename);
		
		$text = '';
		
		$text .= '<b>PHP errors report:</b>'."\n\n";
		$text .= 'Site name: '.str_replace(['#page#',' - '], '', $GLOBALS['config']['site_title'])."\n";
		$text .= 'Server name: '.strtolower($_SERVER['SERVER_NAME'] ?? '')."\n";
		$text .= 'Errors from: '.$email_filename."\n\n";

		if ($has_errors){
			$text .= 'Count - Last seen - Error'."\n";
			foreach($errors as $error) {
				$text .= sprintf('%7s', $error['count']).' - '.$error['times'][(count($error['times']) - 1)].' - '.$error['message'];
			}
		} else {
			$text .= "(No new PHP errors since last report.)\n";
		}
		
		if ($skipped){
			$text .= "\n\nSkipped:\n\n";
			$text .= implode("\n", $skipped);
		}

		// add stats to archive too
		file_put_contents($GLOBALS['config']['base_path'].'cache/php_errors_'.date('Y-m-d_H-i-s').'.log', $text);

		// empty logfile
		file_put_contents($filename, '');
		
		if (!empty($GLOBALS['config']['admin_email'])){

			$this->load->model('cms/cms_email_model');

			$html_text = str_replace(["\n", "\r\n"], '<br>', $text);

			$env = !empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].']' : '';
			$site = str_replace(['#page#',' - '], '', $GLOBALS['config']['site_title']);
			$host = strtolower($_SERVER['SERVER_NAME'] ?? '');

			if ($has_errors){
				$subject = 'PHP errors '.$env.' '.$site.' ('.$host.')';
			} else {
				$subject = 'PHP errors OK '.$env.' '.$site.' ('.$host.')';
			}

			$this->cms_email_model->send_mail(
					$GLOBALS['config']['admin_email'],
					$subject,
					'<html><body>'.$html_text.'</body></html>',
					[
						'is_html' => 1,
						'alt_body' => $text,
						'auto_submitted' => 1,
					]
			);

			$this->_set_last_email_time();

		}

		return [
				'message' => $has_errors
						? 'PHP errors report emailed ('.count($errors).' unique)'
						: 'Empty PHP errors report emailed (daily OK)',
		];
	
	}

}
