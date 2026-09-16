<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_helper_model extends \Model {
	
	function run_cron(){
		
		$cron_data_filename = $GLOBALS['config']['base_path'].'cache/cron.json';
		
		// check if run less than 5 mins ago
		if (file_exists($cron_data_filename) && (time() - filemtime($cron_data_filename)) < 240){
			print('less than 240 s'."\n");
			return;
		}
		
		touch($cron_data_filename);

		// Visit-triggered cron: release session so the long-running request does not
		// block the visitor's other PHP requests (admin, ajax, next page load).
		if (session_status() === PHP_SESSION_ACTIVE){
			session_write_close();
		}
		
		// get data about cron progress
		if (file_exists($cron_data_filename)){
			$cron_data = json_decode(file_get_contents($cron_data_filename), true);
		} else {
			$cron_data = [];
		}
		
		$http_blocks = [];
		$log_blocks = [];

		// get cron tasks
		$this->load->model('cms/cms_page_panel_model');
		$cron_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_cron');

		if (!empty($cron_settings['items'])){
			foreach($cron_settings['items'] as $task){
				
				$period_length = ($task['timeunit'] == 'minute' ? 60 : ($task['timeunit'] == 'hour' ? 3600 : 86400)) * $task['count'];
				
				$time_current = time();
				
				// get start of the current period, when this task should have ideally run
				$time_should = floor($time_current/$period_length) * $period_length;
				
				// check if run - never run or next period for task
				if (empty($cron_data[$task['panel']]) || ($time_should - $cron_data[$task['panel']]['last_expected'] > 0 ) ){
					
					$cron_data[$task['panel']]['last_expected'] = $time_should;
					$cron_data[$task['panel']]['last_real'] = $time_current;
					
					$panel = (string)($task['panel'] ?? '');
					$panel_ci =& get_instance();
					$result = $panel_ci->run_panel_method($panel, 'panel_action');
					unset($panel_ci);

					list($message, $log) = $this->_cron_split_message($result);
					if ($panel !== '' && $message !== ''){
						$block = $panel.":\n".$message;
						$http_blocks[] = $block;
						if ($log){
							$log_blocks[] = $block;
						}
					}
					
				}
				
			}
			
		}
		
		file_put_contents($cron_data_filename, json_encode($cron_data, JSON_PRETTY_PRINT));

		if ($http_blocks){
			print(implode("\n\n", $http_blocks)."\n");
		}
		if ($log_blocks){
			$this->_append_cron_log(implode("\n\n", $log_blocks)."\n");
		}
		
	}

	/**
	 * Last line "noop" = HTTP only, not cron.log. Stripped from displayed text.
	 */
	function _cron_split_message($result){

		if (!is_array($result)){
			$result = [];
		}

		$message = trim((string)($result['message'] ?? ''));
		if ($message !== '' && strtolower($message) === 'ok'){
			$message = '';
		}

		$log = true;
		if ($message !== ''){
			$lines = preg_split("/\r\n|\n|\r/", $message);
			$last = strtolower(trim((string)end($lines)));
			if ($last === 'noop'){
				$log = false;
				array_pop($lines);
				$message = trim(implode("\n", $lines));
			}
		}

		return [$message, $log];

	}

	function _append_cron_log($output){

		$dir = $GLOBALS['config']['base_path'].'cache';
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)){
			error_log_user('CMS error [cms/cron]: cannot create cache/ for cron.log');
			return;
		}

		$block = date('Y-m-d H:i:s')."\n".rtrim($output)."\n\n";
		$ok = @file_put_contents($dir.'/cron.log', $block, FILE_APPEND | LOCK_EX);
		if ($ok === false){
			error_log_user('CMS error [cms/cron]: failed to write cache/cron.log');
		}

	}

}
