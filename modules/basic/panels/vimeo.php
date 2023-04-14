<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class vimeo extends CI_Controller{
	
	function panel_params($params){
// _print_r($params);		
		if (!empty($params['subtitle'])){
			
			$filename = 'sub_'.md5($params['subtitle'].filemtime($GLOBALS['config']['upload_path'].$params['subtitle'])).'.json';
			
			if (!file_exists($GLOBALS['config']['base_path'].'cache/'.$filename)){

				define('SRT_STATE_SUBNUMBER', 0);
				define('SRT_STATE_TIME',      1);
				define('SRT_STATE_TEXT',      2);
				define('SRT_STATE_BLANK',     3);
				
				$lines   = file($GLOBALS['config']['upload_path'].$params['subtitle']);
				
				$subs    = [];
				$state   = SRT_STATE_SUBNUMBER;
				$sub_num  = 0;
				$sub_text = '';
				$sub_time = '';
				
				foreach($lines as $line) {
					switch($state) {
						case SRT_STATE_SUBNUMBER:
							$subNum = trim($line);
							if (!empty($subNum)){
								$state  = SRT_STATE_TIME;
							}
							break;
				
						case SRT_STATE_TIME:
							$sub_time = trim($line);
							$state = SRT_STATE_TEXT;
							break;
				
						case SRT_STATE_TEXT:
							if (trim($line) == '') {
								$sub['number'] = $subNum;
								list($sub['start_time'], $sub['stop_time']) = explode(' --> ', $sub_time);
								$sub['text'] = $sub_text;
								$sub_text = '';
								$state = SRT_STATE_SUBNUMBER;
								$subs[] = $sub;
							} else {
								$sub_text .= $line;
							}
							break;
					}
				}
				
				if ($state == SRT_STATE_TEXT) {
					// if file was missing the trailing newlines, we'll be in this
					// state here.  Append the last read text and add the last sub.
					$sub['text'] = $sub_text;
					$subs[] = $sub;
				}
				
				foreach($subs as $key => $sub){
					
					list($h, $m, $s) = explode(':', $sub['start_time']);
					list($ss, $ms) = explode(',', $s);
					$subs[$key]['start_time'] = ($ms + $s * 1000 + $m * 60000 + $h * 3600000)/1000;
	
					list($h, $m, $s) = explode(':', $sub['stop_time']);
					list($ss, $ms) = explode(',', $s);
					$subs[$key]['stop_time'] = ($ms + $s * 1000 + $m * 60000 + $h * 3600000)/1000;
					
					$subs[$key]['text'] = trim($sub['text']);
				
				}
			
				file_put_contents($GLOBALS['config']['base_path'].'cache/'.$filename, json_encode($subs, JSON_PRETTY_PRINT));
				
			}

			$params['subsfile'] = $GLOBALS['config']['base_url'].'cache/'.$filename;
			
		}

		return $params;
		
	}

}
