<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use MatthiasMullie\Minify;

if ( !function_exists('pack_css')) {
	
	function pack_css_model(){

		$CI =& get_instance();
		$CI->load->model('cms/cms_css_model');

		return $CI->cms_css_model;

	}

	function pack_get_scss_path($scss_filename){

		return pack_css_model()->path($scss_filename);

	}

	function pack_css($scsss){

		return pack_css_model()->pack($scsss);

	}
	function pack_css_inline($scsss){

		return pack_css_model()->pack_inline($scsss);

	}

	function pack_js($js){
	
		// normalise js array
		foreach($js as $key => $value){
			if (!is_array($value)){
				$js[$key] = array(
						'script' => $value,
						'sync' => 'defer',
						'no_pack' => 0,
				);
			} else {
				if (empty($value['no_pack'])){
					$js[$key]['no_pack'] = 0;
				}
				if (!isset($value['sync'])){
					$js[$key]['sync'] = 'defer';
				}
			}
		}
		 
		// get unique
		$js = array_intersect_key($js, array_unique(array_map('serialize', $js)));
	
		$js_strs = array();
		$js_to_cache = array();
		foreach($js as $_js){
	
			$_js['script'] = str_replace('\\', '/', $_js['script']);
	
			// Already-minified vendor files break when re-minified (dash, storefront client, etc.)
			$is_min_js = (bool)preg_match('/\.min\.js(\?|$)/i', $_js['script']);

			if ($_js['sync'] == 'defer' && $GLOBALS['config']['cache']['pack_js'] && empty($_js['no_pack'])
					&& !$is_min_js && substr($_js['script'], 0, 4) !== 'http'){
				$js_to_cache[] = $_js['script'];
			} else if (substr($_js['script'], 0, 4) !== 'http'){ // local script (or no_pack / .min.js)
				$js_strs[] = '<script type="text/javascript" src="'.($GLOBALS['config']['base_site']??'').$GLOBALS['config']['base_url'].$_js['script'].
				(!empty($GLOBALS['config']['cache']['force_download']) ? '?v='.time() : '').'" '.$_js['sync'].'></script>';
			} else { // outside script
				$js_strs[] = '<script type="text/javascript" src="'.$_js['script'].'" '.$_js['sync'].'></script>';
			}
	
		}
	
		$js_cache = '';
		if (!empty($js_to_cache)){
				
			$hash = substr(md5(implode(' ', $js_to_cache)), 0, 8);
			// check if any of files is changed
			$filename = cms_path('cache').$hash.'.js';
			$fileurl = ($GLOBALS['config']['base_site']??'').cms_path_url('cache').$hash.'.js';
				
			if (file_exists($filename)){
				$filetime = filemtime($filename);
				$max_scripttime = 0;
				foreach($js_to_cache as $js_file){
					$js_file = $GLOBALS['config']['base_path'] . trim($js_file, '/');
					$max_scripttime = max(filemtime($js_file), $max_scripttime);
				}
			} else {
				$filetime = 0;
			}
				
			// if new js cache generation needed
			if (!file_exists($filename) || $max_scripttime > $filetime){
	
				touch($filename);
	
				// load all js files
				$js_string = '';
				foreach($js_to_cache as $js_file){
					$js_file = $GLOBALS['config']['base_path'] . trim($js_file, '/');
					$js_file_content = trim(file_get_contents($js_file));
						
					// if file ends with ) add ;
					if (mb_substr($js_file_content, -1) == ')'){
						$js_file_content .= ';';
					}
						
					$js_string .= $js_file_content."\n";
				}

				// js (css) minifier
				require_once($GLOBALS['config']['base_path'].'system/vendor/minify/src/Minify.php');
				require_once($GLOBALS['config']['base_path'].'system/vendor/minify/src/JS.php');
				
				$minifier = new Minify\JS($js_string);
				 
				try {
					
					$minifier->minify($filename);
				
				} catch (Exception $e) {
					
					if (!empty($GLOBALS['config']['errors_visible'])){
						_html_error('JS Minifier error:<br>Message: Error minifying JavaScript!');
					}
					
					file_put_contents($filename, $js_string);
					
				}
	
			}
				
			$js_cache = '<script type="text/javascript" src="'.$fileurl.
			(!empty($GLOBALS['config']['cache']['force_download']) ? '?v='.time() : '').'" defer></script>';
	
		}
	
		return implode("\n", $js_strs)."\n".$js_cache;
	
	}

	function pack_js_inline($js){

		if (empty($js)){
			return '';
		}

		foreach ($js as $key => $value){
			if (!is_array($value)){
				$js[$key] = [
					'script' => $value,
					'sync' => 'defer',
					'no_pack' => 0,
				];
			} else {
				if (empty($value['no_pack'])){
					$js[$key]['no_pack'] = 0;
				}
				if (!isset($value['sync'])){
					$js[$key]['sync'] = 'defer';
				}
			}
		}

		$js = array_intersect_key($js, array_unique(array_map('serialize', $js)));
		$js_string = '';

		foreach ($js as $_js){

			$_js['script'] = str_replace('\\', '/', $_js['script']);

			if (substr($_js['script'], 0, 4) === 'http'){
				continue;
			}

			$js_file = $GLOBALS['config']['base_path'].trim($_js['script'], '/');

			if (!is_file($js_file)){
				continue;
			}

			$js_file_content = trim(file_get_contents($js_file));

			if (mb_substr($js_file_content, -1) === ')'){
				$js_file_content .= ';';
			}

			$js_string .= $js_file_content."\n";

		}

		$js_string = trim($js_string);

		if ($js_string === ''){
			return '';
		}

		if (!empty($GLOBALS['config']['cache']['pack_js'])){

			require_once($GLOBALS['config']['base_path'].'system/vendor/minify/src/Minify.php');
			require_once($GLOBALS['config']['base_path'].'system/vendor/minify/src/JS.php');

			$minifier = new Minify\JS($js_string);

			try {
				return $minifier->minify();
			} catch (Exception $e){
				return $js_string;
			}

		}

		return $js_string;

	}
	
	function add_js($file){

		if (!empty($file['script'])){
			$filename = $file['script'];
		} else {
			$filename = $file;
		}

		if (empty($GLOBALS['_panel_js'])){
			$GLOBALS['_panel_js'] = [];
		}

		$exists = false;
		foreach ($GLOBALS['_panel_js'] as $entry){
			if ($entry === $file || $entry === $filename || (!empty($entry['script']) && $entry['script'] === $filename)){
				$exists = true;
				break;
			}
		}

		if (!$exists){
			$GLOBALS['_panel_js'][] = $file;
		}

	}

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
