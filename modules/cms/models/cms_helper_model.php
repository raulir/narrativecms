<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_helper_model extends CI_Model {
	
	function run_cron(){
		
		$cron_data_filename = $GLOBALS['config']['base_path'].'cache/cron.json';
		
		// check if run less than 5 mins ago
		if (file_exists($cron_data_filename) && (time() - filemtime($cron_data_filename)) < 240){
			print('less than 240 s'."\n");
			return;
		}
		
		touch($cron_data_filename);
		
		// get data about cron progress
		if (file_exists($cron_data_filename)){
			$cron_data = json_decode(file_get_contents($cron_data_filename), true);
		} else {
			$cron_data = [];
		}
		
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
					
					// run the task
					$panel_ci =& get_instance();
					$panel_ci->run_panel_method($task['panel'], 'panel_action');
					unset($panel_ci);
					
				}
				
			}
			
		}
		
		file_put_contents($cron_data_filename, json_encode($cron_data, JSON_PRETTY_PRINT));
		
		print('ok');
		
	}

}
