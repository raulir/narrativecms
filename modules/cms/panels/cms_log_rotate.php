<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_log_rotate extends MY_Controller{

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
		
		$text .= 'PHP errors from '.$email_filename."\n\n";
		$text .= '  Count         Last seen        Error'."\n";
		foreach($errors as $error) {
			$text .= sprintf('%7s', $error['count']).'   '.$error['times'][(count($error['times']) - 1)].'   '.$error['message'];
		}

		// add stats to archive too
		file_put_contents($GLOBALS['config']['base_path'].'cache/php_errors_'.date('Y-m-d_H-i-s').'.log', $text);

		// empty logfile
		file_put_contents($filename, '');
		
		if (!empty($GLOBALS['config']['email'])){
		
			@mail($GLOBALS['config']['email'], 'PHP errors from '.$email_filename, $text, 'From: ' . $GLOBALS['config']['email'] . "\r\n");
		
		}
		
		// print('<pre>');
		// print_r($text);
	
	}

}
