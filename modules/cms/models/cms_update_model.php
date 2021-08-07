<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_update_model extends CI_Model {

	// rebuilds area hash caches
	function rebuild_area($area, $folders = []){
		
		if (empty($area)){

			// system and cms
			$folders = [
					'application/',
					'js/',
					'system/',
					'modules/cms/',
			];

		}

		$return = ['area' => $area];
		
		$extensions = [
				'',
				'bin',
				'css',
				'dist',
				'eot',
				'gif',
				'htaccess',
				'htc',
				'html',
				'js',
				'json',
				'md',
				'otf',
				'php',
				'png',
				'scss',
				'svg',
				'ttf',
				'txt',
				'woff',
				'jpg',
		];
		
		$hashes = [];
		$version_hashes = array();
		$current_hash = '';
		
		// go over all folders
		foreach($folders as $folder){
			
			// go over all files recursively
			$full_folder = str_replace("\\", '/', $GLOBALS['config']['base_path'].$folder);

			if (file_exists($full_folder)){
			
				$it = new RecursiveDirectoryIterator($full_folder);
				foreach (new RecursiveIteratorIterator($it) as $filename => $file) {
					
	    			$cms_filename = $folder.str_replace($full_folder, '', str_replace("\\", '/', $filename));
	 				
	 				if(!is_dir($filename) && in_array(pathinfo($cms_filename, PATHINFO_EXTENSION), $extensions)){

	 					// if matches, get hash
						$cms_md5 = md5_file($filename);
				
						// add to hashes
						$hashes[] = array(
							'filename' => $cms_filename,
							'hash' => $cms_md5,
							'size' => filesize($filename),
						);
						
						$version_hashes[] = $cms_md5;
	    				
					}
					
				}
				
			}
			
		}
		
		// special case for system and cms
		if (empty($area)){
		
			// index.php
			$cms_md5 = md5_file(str_replace("\\", '/', $GLOBALS['config']['base_path']).'index.php');
			$hashes[] = array(
					'filename' => 'index.php',
					'hash' => $cms_md5,
					'size' => filesize(str_replace("\\", '/', $GLOBALS['config']['base_path']).'index.php'),
			);
			$version_hashes[] = $cms_md5;
			
			// LICENSE
			if (file_exists(str_replace("\\", '/', $GLOBALS['config']['base_path']).'LICENSE')){
				$cms_md5 = md5_file(str_replace("\\", '/', $GLOBALS['config']['base_path']).'LICENSE');
				$hashes[] = array(
						'filename' => 'LICENSE',
						'hash' => $cms_md5,
						'size' => filesize(str_replace("\\", '/', $GLOBALS['config']['base_path']).'LICENSE'),
				);
				$version_hashes[] = $cms_md5;
			}
			
			$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';

		} else {
			
			$filename = $GLOBALS['config']['base_path'] . 'cache/version_'.$area.'.json';
			
		}
		
		sort($version_hashes);
		
		$current_hash = md5(implode($version_hashes));
		
		// load current version data, if exists
		if (file_exists($filename)){
			$old_data = json_decode(file_get_contents($filename), true);
		} else {
			$old_data = array();
		}
		
		if (empty($old_data['version'])){
			$old_data['version'] = '0.0.0';
		}
		
		if (empty($old_data['current_hash']) || $current_hash !== $old_data['current_hash']){
			
			$new_data = [
					'version' => $old_data['version'],
					'version_hash' => !empty($old_data['version_hash']) ? $old_data['version_hash'] : '[unknown]',
					'version_time' => !empty($old_data['version_time']) ? $old_data['version_time'] : '0',
					'update_time' => !empty($old_data['update_time']) ? $old_data['update_time'] : '0',
					'current_hash' => $current_hash,
					'files' => $hashes,
			];
			
			// write hashes to hash cache
			file_put_contents($filename, json_encode($new_data, JSON_PRETTY_PRINT));
		
		}
		
		$return['local_version'] = $old_data['version'];
		$return['local_updated'] = !empty($old_data['update_time']) ? $old_data['update_time'] : '0';
		$return['local_version_time'] = !empty($old_data['version_time']) ? $old_data['version_time'] : '0';
		$return['local_current_hash'] = $current_hash;
		$return['local_version_hash'] = !empty($old_data['version_hash']) ? $old_data['version_hash'] : '[unknown]';
		
		return $return;
		
	}

	// rebuild all areas
	function rebuild(){
		
		$return = [];		
		$return[] = $this->rebuild_area('');
		
		// other areas
		$this->load->model('cms/cms_module_model');
		
		$areas = $this->cms_module_model->get_modules();
		
		foreach($areas as $area){
			if ($area['name'] !== 'cms'){
				
				$return[] = $this->rebuild_area($area['name'], ['modules/'.$area['name'].'/']);
				
			}
		}
		
		return $return;

	}
	
	function increment_master_version($area){
		
		if (empty($area)){
			$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		} else {
			$filename = $GLOBALS['config']['base_path'] . 'cache/version_'.$area.'.json';
		}
		
		// load cache file
		$data = json_decode(file_get_contents($filename), true);
		
		list($maj, $min, $num) = explode('.', $data['version']);
		
		// TODO: get maj and min from area config
		
		if ($maj != $maj){ // from config
			$min = 0;
			$num = 0;
		}
		
		if ($min != $min){ // from config
			$num = 0;
		}
		
		$data['version'] = $maj.'.'.$min.'.'.($num + 1);
		$data['version_hash'] = $data['current_hash'];
		
		$data['version_time'] = time();
		$data['update_time'] = time();
		
		// write cache file
		file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
		
	}
	
	function get_version($area){
		
		if (empty($area)){
			$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		} else {
			$filename = $GLOBALS['config']['base_path'] . 'cache/version_'.$area.'.json';
		}
		
		// load current version data, if exists
		if (!file_exists($filename)){

			if (!empty($area)){
				if (file_exists($GLOBALS['config']['base_path'].'modules/'.$area['name'].'/')){
					
					$this->rebuild_area($area, ['modules/'.$area['name'].'/']);
					
				}
			} else {
				
				$this->rebuild_area('');
				
			}

		}
		
		$old_data = json_decode(file_get_contents($filename), true);
		$return = array(
			'version_hash' => $old_data['version_hash'],
			'current_hash' => $old_data['current_hash'],
			'version_time' => !empty($old_data['version_time']) ? $old_data['version_time'] : 0,
			'update_time' => !empty($old_data['update_time']) ? $old_data['update_time'] : 0,
			'version' => $old_data['version'],
		);
		
		return $return;
		
	}
	
	function get_master_version($area = ''){
		
	    if (empty($GLOBALS['config']['cms_update_url'])){
    		return false;
    	}
    	
		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];

		$postdata = http_build_query([
				'do' => 'version',
				'module' => $area,
				'area' => $area,
		]);
    	
    	// check url
    	if (stristr($GLOBALS['config']['cms_update_url'], 'localhost')){
    		$host = parse_url($GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
    		$url = str_replace($host, 'localhost', $GLOBALS['config']['cms_update_url']);
    		$header[] = 'Host: '.$host;
    	} else {
    		$url = $GLOBALS['config']['cms_update_url'];
    	}
    	
    	$context  = stream_context_create(array('http' => array(
    			'method'  => 'POST',
    			'header'  => $header,
    			'content' => $postdata
    	)));
    	 
    	$master_data = file_get_contents($url, false, $context);

    	if ($master_data === false){
			return [];
		}
		
		return json_decode($master_data, true);
		
	}
	
	function get_files($area){
		
		if (empty($area)){
			$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		} else {
			$filename = $GLOBALS['config']['base_path'] . 'cache/version_'.$area.'.json';
		}
		
		// load current version data, if exists
		if (file_exists($filename)){
			$old_data = json_decode(file_get_contents($filename), true);
			$return = array(
				'files' => $old_data['files'],
			);
		} else {
			$return = array(
				'files' => '',
				'error' => 'No version information, rebuild master first',
			);
		}
		
		return $return;

	}
	
	function get_master_files($area){
		
		if (empty($GLOBALS['config']['cms_update_url'])){
			return false;
		}
		
		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];
		
		$postdata = http_build_query([
				'do' => 'files',
				'module' => $area,
				'area' => $area,
		]);
		 
		// check url
		if (stristr($GLOBALS['config']['cms_update_url'], 'localhost')){
			$host = parse_url($GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
			$url = str_replace($host, 'localhost', $GLOBALS['config']['cms_update_url']);
			$header[] = 'Host: '.$host;
		} else {
			$url = $GLOBALS['config']['cms_update_url'];
		}
		 
		$context  = stream_context_create(array('http' => array(
				'method'  => 'POST',
				'header'  => $header,
				'content' => $postdata
		)));
		
		$master_data = file_get_contents($url, false, $context);
		
		if (empty($master_data)){
			return false;
		}
		
		return json_decode($master_data, true);
		
	}

	function get_file($needed_filename, $area){
		
		if (empty($area)){
			$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		} else {
			$filename = $GLOBALS['config']['base_path'] . 'cache/version_'.$area.'.json';
		}
		
		// load current version data, if exists
		if (file_exists($filename)){
			
			$old_data = json_decode(file_get_contents($filename), true);
			$files = $old_data['files'];
			
			// check if in files
			$in = false;
			foreach($files as $file){
				if ($file['filename'] == $needed_filename){
					$in = true;
				}
			}
			
			if($in){
				$return = array(
					'file' => base64_encode(file_get_contents($GLOBALS['config']['base_path'] . $needed_filename)),
				);
			} else {
				$return = array(
					'file' => '',
					'error' => 'No such file',
				);
			}
			
		} else {
			$return = array(
				'file' => '',
				'error' => 'No version information, rebuild master first',
			);
		}
		
		return $return;
		
	}
	
	function get_master_file($needed_filename, $area){
		
		if (empty($GLOBALS['config']['cms_update_url'])){
			return false;
		}
		
		$header = [
				'Content-type: application/x-www-form-urlencoded',
		];
		
		$postdata = http_build_query([
				'do' => 'file',
				'area' => $area,
				'module' => $area,
				'filename' => $needed_filename,
		]);
			
		// check url
		if (stristr($GLOBALS['config']['cms_update_url'], 'localhost')){
			$host = parse_url($GLOBALS['config']['cms_update_url'], PHP_URL_HOST);
			$url = str_replace($host, 'localhost', $GLOBALS['config']['cms_update_url']);
			$header[] = 'Host: '.$host;
		} else {
			$url = $GLOBALS['config']['cms_update_url'];
		}
			
		$context  = stream_context_create(array('http' => array(
				'method'  => 'POST',
				'header'  => $header,
				'content' => $postdata
		)));
		
		$master_data = file_get_contents($url, false, $context);
		
		return json_decode($master_data, true);

	}
	
	function get_needed_files($area){
		
		// get master list
		$master_files = $this->get_master_files($area);

		// get local list
		$local_files = $this->get_files($area);
		
		$return = array();

		// remove 
		foreach($master_files['files'] as $master_key => $master_file){
				
			$needs_update = false;
			$local_file_found = false;
				
			$master_hash = $master_file['hash'];
				
			// find the same local file
			foreach($local_files['files'] as $local_key => $local_file){
		
				if ($local_file['filename'] == $master_file['filename']){
					$local_file_found = true;
					$local_key_to_delete = $local_key;
					if ($local_file['hash'] != $master_file['hash']){
						$needs_update = true;
						$local_hash = $local_file['hash'];
						$letter = 'U';
					}
				}
		
			}
				
			if ($local_file_found){
				$local_hash = $local_files['files'][$local_key_to_delete]['hash'];
				unset($local_files['files'][$local_key_to_delete]);
			} else {
				$local_hash = 'no local file';
				$needs_update = true;
				$letter = 'A';
			}
				
			if ($needs_update){
				
				$return[] = array(
					'filename' => $master_file['filename'],
					'letter' => $letter,
					'fn_hash' => md5($master_file['filename']),
				);
		
			}
				
		}
		
		// delete local files not in master list
		foreach($local_files['files'] as $local_file){
				
			$return[] = array(
				'filename' => $local_file['filename'],
				'letter' => 'D',
				'fn_hash' => md5($local_file['filename']),
			);
				
		}
		
		return $return;
		
	}
	
	// copies files from server to local cache
	function update_file($filename, $area){
		
		// get remote file
		$master_file_data = $this->get_master_file($filename, $area);

		// create cache folder if not exists
		$pathinfo = pathinfo($GLOBALS['config']['base_path'] . 'cache/update/' . $filename);
		if (!file_exists($pathinfo['dirname'])) {
			mkdir($pathinfo['dirname'], 0777, true);
		}
		
		if (!empty($master_file_data['file'])){

			// create folder if not exists
			$pathinfo = pathinfo($GLOBALS['config']['base_path'] . $filename);
			if (!file_exists($pathinfo['dirname'])) {
				mkdir($pathinfo['dirname'], 0777, true);
			}
			
			// replace local file
			$file_content = base64_decode($master_file_data['file']);
			// file_put_contents($GLOBALS['config']['base_path'] . $filename, $file_content);
			file_put_contents($GLOBALS['config']['base_path'] . 'cache/update/' . $filename, $file_content);
				
		} else {
		
			// unlink($GLOBALS['config']['base_path'] . $filename);
			file_put_contents($GLOBALS['config']['base_path'] . 'cache/update/' . $filename, '_DELETE_');
				
		}
		
		return $filename;
		
	}
	
	function update_copy($area){

		// go over all cache files recursively
		$folder = $GLOBALS['config']['base_path']. 'cache/update/';
		
		if (!empty($area)){
			$folder_area = $folder.'modules/'.$area.'/';
		} else {
			$folder_area = $folder;
		}

		if (file_exists($folder_area)){
			
			$it = new RecursiveDirectoryIterator($folder);
			foreach (new RecursiveIteratorIterator($it) as $filename => $file) {
				
				$from_filename = $folder . str_replace($folder, '', str_replace("\\", '/', $filename));

				if (!is_dir($from_filename)){
						
					$to_filename = $GLOBALS['config']['base_path'] . str_replace($folder, '', str_replace("\\", '/', $filename));

					print($from_filename.' '.$to_filename."\n");
					
					// check whats inside
					$contents = file_get_contents($from_filename);

					if ($contents == '_DELETE_'){
						if (file_exists($to_filename)){
							unlink($to_filename);
						}
					} else {
						copy($from_filename, $to_filename);
					}
					
					unlink($from_filename);
				
				}
	
			}

			// delete directory in cache
			function rrmdir($dir) {
				if (is_dir($dir)) {
					$objects = scandir($dir);
					foreach ($objects as $object) {
						if ($object != "." && $object != "..") {
							if (is_dir($dir."/".$object))
								rrmdir($dir."/".$object);
								else
									unlink($dir."/".$object);
						}
					}
					rmdir($dir);
				}
			}
			rrmdir($folder);

		}

	}
	
	function update_version_cache($area, $params){

		// load cache file
		if (empty($area)){
			$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		} else {
			$filename = $GLOBALS['config']['base_path'] . 'cache/version_'.$area.'.json';
		}
		
		$data = json_decode(file_get_contents($filename), true);
		
		// change values
		$data['version'] = $params['version'];
		$data['version_hash'] = $params['version_hash'];
		$data['version_time'] = $params['version_time'];
		$data['update_time'] = $params['update_time'];
		
		// write cache file
		file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

	}

	function run_sql($sql){
		
		$query = $this->db->query($sql);
		
		if ($query === false || $query === true){
			return [$query];
		}

		$result = $query->result_array();
		
		return $result;
		
	}
	
	function up(){
		
		// check if table block exists and update
		
		$sql = "select 1 from block limit 1";
		$query = $this->db->query($sql);
		
		if ($query && $query->num_rows()){
			
			$sql = "rename table `block` to `cms_page_panel`";
			$this->run_sql($sql);
			
			$sql = "ALTER TABLE `cms_page_panel` CHANGE `block_id` `cms_page_panel_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT";
			$this->run_sql($sql);
			
			$sql = "ALTER TABLE `cms_page_panel` CHANGE `page_id` `cms_page_id` INT(10) UNSIGNED NOT NULL";
			$this->run_sql($sql);
				
		}
		
	}
	
	function update_cleanup(){
		
		$return = 0;
		
		$folders = [
				'application/',
				'js/',
				'system/',
				'modules/cms/',
		];

		// go over all folders
		foreach($folders as $folder){
				
			// go over all files recursively
			$full_folder = str_replace("\\", '/', $GLOBALS['config']['base_path'].$folder);
				
			if (file_exists($full_folder)){
					
				$it = new RecursiveDirectoryIterator($full_folder);
				foreach (new RecursiveIteratorIterator($it) as $filename => $file) {
						
					if (is_dir($filename) && substr($filename, -3, 3) !== '/..'){
						
						$fi = new FilesystemIterator($filename, FilesystemIterator::SKIP_DOTS);
						if (!iterator_count($fi)) {
							rmdir($filename);
						}
						
					}
						
				}
		
			}
				
		}
	}

}