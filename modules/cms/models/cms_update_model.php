<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_update_model extends CI_Model {
	
	function rebuild($params = array()){

		$folders = array(
			'application/',
			'js/',
			'system/',
			'modules/cms/',
		);
		
		$extensions = array(
			'css',
			'scss',
			'js',
			'json',
			'php',
			'png',
			'ttf',
			'otf',
			'eot',
			'woff',
			'svg',
			'html',
			'htaccess',
			'md',
			'bin',
		);
		
		$hashes = array();
		$version_hashes = array();
		$version_hash = '';
		
		// go over all folders
		foreach($folders as $folder){
			
			// go over all files recursively
			$full_folder = str_replace("\\", '/', $GLOBALS['config']['base_path'].$folder);
			
			$it = new RecursiveDirectoryIterator($full_folder);
			foreach (new RecursiveIteratorIterator($it) as $filename => $file) {
				
    			$cms_filename = $folder.str_replace($full_folder, '', str_replace("\\", '/', $filename));
 				
 				if(in_array(pathinfo($cms_filename, PATHINFO_EXTENSION), $extensions)){

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
		
		sort($version_hashes);
		
		$version_hash = md5(implode($version_hashes));
		$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		
		// load current version data, if exists
		if (file_exists($filename)){
			$old_data = json_decode(file_get_contents($filename), true);
		} else {
			$old_data = array();
		}
		
		if (empty($old_data['version_hash']) || $version_hash !== $old_data['version_hash']){
		
			// if master, increase version number
			if (!empty($GLOBALS['config']['update']['is_master'])){
				$version_major = !empty($GLOBALS['config']['update']['version_major']) ? $GLOBALS['config']['update']['version_major'] : '0';
				$version_minor = !empty($GLOBALS['config']['update']['version_minor']) ? $GLOBALS['config']['update']['version_minor'] : '0';
				$version_number = isset($old_data['version_number']) ? ($old_data['version_number'] + 1).'' : '0';
				$version = $version_major.'.'.$version_minor.'.'.$version_number;
				$version_last = $version;
			} else {
				if (!empty($params['version'])){
					list($old_data['version_major'], $old_data['version_minor'], $old_data['version_number']) = explode('.', $params['version']);
					$old_data['version_last'] = $params['version'];
					$version_hash = $params['hash'];
				}
				$version_major = !empty($old_data['version_major']) ? $old_data['version_major'] : '0';
				$version_minor = !empty($old_data['version_minor']) ? $old_data['version_minor'] : '0';
				$version_number = !empty($old_data['version_number']) ? $old_data['version_number'] : '0';
				$version = 'custom';
				$version_last = !empty($old_data['version_last']) ? $old_data['version_last'] : '0';
			}
			
			// write hashes to hash cache
			$this->load->model('cms_helper_model');
			file_put_contents($filename, $this->cms_helper_model->json_format(array(
				'version' => $version_major.'.'.$version_minor.'.'.$version_number,
				'version_major' => $version_major,
				'version_minor' => $version_minor,
				'version_number' => $version_number,
				'version_last' => $version_last,
				'version_hash' => $version_hash,
				'files' => $hashes,
			)));
		
		}

	}
	
	function get_version(){
		
		$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		
		// load current version data, if exists
		if (file_exists($filename)){
			$old_data = json_decode(file_get_contents($filename), true);
			$return = array(
				'hash' => $old_data['version_hash'],
				'version' => $old_data['version'],
			);
		} else {
			$return = array(
				'hash' => '',
				'version' => '',
				'error' => 'No version information, rebuild master first',
			);
		}
		
		return $return;
		
	}
	
	function get_master_version(){

		$postdata = http_build_query(array('do' => 'version', ));
		$context  = stream_context_create(array('http' => array(
		        'method'  => 'POST',
        		'header'  => 'Content-type: application/x-www-form-urlencoded',
        		'content' => $postdata
    	)));
    	
    	if (empty($GLOBALS['config']['cms_update_url'])){
    		return false;
    	}

		$master_data = file_get_contents($GLOBALS['config']['cms_update_url'], false, $context);
		
		return json_decode($master_data, true);
		
	}
	
	function get_files(){
		
		$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		
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
	
	function get_master_files(){
		
		$postdata = http_build_query(array('do' => 'files', ));
		$context  = stream_context_create(array('http' => array(
		        'method'  => 'POST',
        		'header'  => 'Content-type: application/x-www-form-urlencoded',
        		'content' => $postdata
    	)));

		if (!empty($GLOBALS['config']['cms_update_url'])){
			$master_data = file_get_contents($GLOBALS['config']['cms_update_url'], false, $context);
		}
		
		if (empty($master_data)){
			return false;
		}
		
		return json_decode($master_data, true);
		
	}

	function get_file($needed_filename){
		
		$filename = $GLOBALS['config']['base_path'] . 'cache/version.json';
		
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
	
	function get_master_file($needed_filename){

		$postdata = http_build_query(array('do' => 'file', 'filename' => $needed_filename, ));
		$context  = stream_context_create(array('http' => array(
		        'method'  => 'POST',
        		'header'  => 'Content-type: application/x-www-form-urlencoded',
        		'content' => $postdata
    	)));
		
		// if fails to load configuration in db use master copy
		if (empty($GLOBALS['config']['cms_update_url'])){
			$GLOBALS['config']['cms_update_url'] = 'http://www.bytecrackers.com/cms/cms/updater/';
		}

		$master_data = file_get_contents($GLOBALS['config']['cms_update_url'], false, $context);
		
		return json_decode($master_data, true);

	}
	
	function get_needed_files(){
		
		// get master list
		$master_files = $this->get_master_files();
		
		// get local list
		$local_files = $this->get_files();
		
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
	function update_file($filename){
		
		// get remote file
		$master_file_data = $this->get_master_file($filename);

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
	
	function update_copy(){
		
		// go over all cache files recursively
		$folder = $GLOBALS['config']['base_path']. 'cache/update/';
			
		$it = new RecursiveDirectoryIterator($folder);
		foreach (new RecursiveIteratorIterator($it) as $filename => $file) {
		
			$from_filename = $folder . str_replace($folder, '', str_replace("\\", '/', $filename));
			
			if (!is_dir($from_filename)){
			
				$to_filename = $GLOBALS['config']['base_path'] . str_replace($folder, '', str_replace("\\", '/', $filename));
					
				// check whats inside
				$contents = file_get_contents($from_filename);
				if ($contents == '_DELETE_'){
					unlink($to_filename);
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
	
	// deprecated !!!
	function update(){ 

		// get master list
		$master_files = $this->get_master_files();
		
		if (empty($master_files)){
			return array('error' => 'Update failed, check settings', );
		}
		
// print_r($master_files);		
		// get local list
		$local_files = $this->get_files();
// print_r($local_files);		

		$return = array();
		
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
//				print('x ');
				$needs_update = true;
				$letter = 'A';
			}
			
			if ($needs_update){
				// get remote file
				$master_file_data = $this->get_master_file($master_file['filename']);

// print($local_hash.' '.$master_hash.' '.$master_file['filename'].'<br>');

				// create folder if not exists
				$pathinfo = pathinfo($GLOBALS['config']['base_path'] . $master_file['filename']);
				if (!file_exists($pathinfo['dirname'])) {
					mkdir($pathinfo['dirname'], 0777, true);
				}
				
				// replace local file
				$file_content = base64_decode($master_file_data['file']);
				file_put_contents($GLOBALS['config']['base_path'] . $master_file['filename'], $file_content);
				
				$return[] = $letter . ' ' . $master_file['filename'];
				
			}
			
		}
		
		// delete local files not in master list
		foreach($local_files['files'] as $local_file){
			
			unlink($GLOBALS['config']['base_path'] . $local_file['filename']);
			$return[] = 'D ' . $local_file['filename'];
			
		}
		
		// update local file list
		$master_version = $this->get_master_version();
		
		if (!empty($master_version)){
			$this->rebuild(array('version' => $master_version['version'], 'hash' => $master_version['hash'], ));
		}
		
		return $return;
	
	}

}