<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_log_rotate extends CI_Controller {

	function panel_action(){
		
		if (empty($GLOBALS['config']['errors_log'])){
			return;
		}
		
		$explode_str = '] ';
		$trim_str = '[';
				
		$filename = $GLOBALS['config']['errors_log'];
		
		$lines = file($filename);
		
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
		$text .= 'Site name: '.str_replace(['#page#',' - '], '', $GLOBALS['config']['site_title'])."\n";
		$text .= 'Server name: '.strtolower($_SERVER['SERVER_NAME'])."\n";
		$text .= 'Errors from: '.$email_filename."\n\n";
		$text .= 'Count - Last seen - Error'."\n";
		foreach($errors as $error) {
			$text .= sprintf('%7s', $error['count']).' - '.$error['times'][(count($error['times']) - 1)].' - '.$error['message'];
		}
		
		$text .= "\n\nSkipped:\n\n";
		$text .= implode("\n", $skipped);

		// add stats to archive too
		file_put_contents($GLOBALS['config']['base_path'].'cache/php_errors_'.date('Y-m-d_H-i-s').'.log', $text);

		// empty logfile
		file_put_contents($filename, '');
		
		if (!empty($GLOBALS['config']['admin_email'])){

			$this->load->model('cms/cms_email_model');

			$html_text = str_replace(["\n", "\r\n"], '<br>', $text);

			$subject = 'PHP errors '.(!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].']' : '').' '.
					str_replace(['#page#',' - '], '', $GLOBALS['config']['site_title']).' ('.strtolower($_SERVER['SERVER_NAME']).')';

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

		}
	
	}

}
