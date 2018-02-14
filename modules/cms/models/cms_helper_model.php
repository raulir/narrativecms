<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_helper_model extends CI_Model {
	
	function run_cron(){
		
		$cron_data_filename = $GLOBALS['config']['base_path'].'cache/cron.json';
		
		// check if run less than 5 mins ago
		if (file_exists($cron_data_filename) && (time() - filemtime($cron_data_filename)) < 240){
			print('less than 240 s');
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

	// TODO: DEPRECATED
	/**
	* Format a flat JSON string to make it more human-readable
	*
	* @param string $json The original JSON string to process
	*        When the input is not a string it is assumed the input is RAW
	*        and should be converted to JSON first of all.
	* @return string Indented version of the original JSON string
	*/
	function json_format($json) {
	  	if (!is_string($json)) {
	    	if (phpversion() && phpversion() >= 5.4) {
	      		return json_encode($json, JSON_PRETTY_PRINT);
	    	}
	    	$json = json_encode($json);
	  	}
	  	$result      = '';
	  	$pos         = 0;               // indentation level
	  	$strLen      = strlen($json);
	  	$indentStr   = "\t";
	  	$newLine     = "\n";
	  	$prevChar    = '';
	  	$outOfQuotes = true;
	  	for ($i = 0; $i < $strLen; $i++) {
	    	// Speedup: copy blocks of input which don't matter re string detection and formatting.
	    	$copyLen = strcspn($json, $outOfQuotes ? " \t\r\n\",:[{}]" : "\\\"", $i);
	    	if ($copyLen >= 1) {
	    	  	$copyStr = substr($json, $i, $copyLen);
	      		// Also reset the tracker for escapes: we won't be hitting any right now
	      		// and the next round is the first time an 'escape' character can be seen again at the input.
	      		$prevChar = '';
	      		$result .= $copyStr;
	      		$i += $copyLen - 1;      // correct for the for(;;) loop
	      		continue;
	    	}
	    
	    	// Grab the next character in the string
	    	$char = substr($json, $i, 1);
	    
	    	// Are we inside a quoted string encountering an escape sequence?
	    	if (!$outOfQuotes && $prevChar === '\\') {
	      		// Add the escaped character to the result string and ignore it for the string enter/exit detection:
	      		$result .= $char;
	      		$prevChar = '';
	      		continue;
	    	}
	    	
	    	// Are we entering/exiting a quoted string?
	    	if ($char === '"' && $prevChar !== '\\') {
	      		$outOfQuotes = !$outOfQuotes;
	    	}
	    	
	    	// If this character is the end of an element,
	    	// output a new line and indent the next line
	    	else if ($outOfQuotes && ($char === '}' || $char === ']')) {
	      		$result .= $newLine;
	      		$pos--;
	      		for ($j = 0; $j < $pos; $j++) {
	        		$result .= $indentStr;
	      		}
	    	}
	    	
	    	// eat all non-essential whitespace in the input as we do our own here and it would only mess up our process
	    	else if ($outOfQuotes && false !== strpos(" \t\r\n", $char)) {
	      		continue;
	    	}
	    	
	    	// Add the character to the result string
	    	$result .= $char;
	    	// always add a space after a field colon:
	    	if ($outOfQuotes && $char === ':') {
	      		$result .= ' ';
	    	}
	    	// If the last character was the beginning of an element,
	    	// output a new line and indent the next line
	    	else if ($outOfQuotes && ($char === ',' || $char === '{' || $char === '[')) {
	      		$result .= $newLine;
	      		if ($char === '{' || $char === '[') {
	        		$pos++;
	      		}
	      		for ($j = 0; $j < $pos; $j++) {
	        		$result .= $indentStr;
	      		}
	    	}
	    	$prevChar = $char;
	  	}

	  	return $result;
	  	
	}

}
