<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_css_model extends model {

	function add_css($file){
	
		if (!empty($file['script'])){
			$filename = $file['script'];
		} else {
			$filename = $file;
		}
	
		// for module/file.scss format
		if (!is_array($file) && substr_count($filename, '/') == 1){
				
			$file = [
					'script' => 'modules/'.str_replace('/', '/css/', $filename),
			];
				
			list($module, $file_short) = explode('/', $filename);
				
			if (file_exists($GLOBALS['config']['base_path'].'modules/'.$module.'/css/'.$module.'.scss')){
				$file['related'] = ['modules/'.$module.'/css/'.$module.'.scss'];
			}
				
		}
	
		if (empty($GLOBALS['_panel_scss'])){
			$GLOBALS['_panel_scss'] = [];
			$GLOBALS['_panel_scss_names'] = [];
		}
	
		if (!in_array($file, $GLOBALS['_panel_scss_names'])){
			$GLOBALS['_panel_scss'][] = $file;
			$GLOBALS['_panel_scss_names'][] = $filename;
		}
	
	}
	
}
