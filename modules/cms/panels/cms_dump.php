<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_dump extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}
	
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
	
	function panel_params($params){
		
		$tables = ['block', 'cms_file', 'cms_image', 'cms_keyword', 'cms_page', 'cms_page_panel_param', 'cms_search_cache', 'cms_slug', 'cms_text', 'cms_user', 'menu_item', ];
		
		$outfile = $GLOBALS['config']['base_path'].'cache/_resources.zip';
		$outfile2 = $GLOBALS['config']['base_path'].'cache/_database.sql';
		$outfile2z = $GLOBALS['config']['base_path'].'cache/_database.zip';
		
		if (!empty($params['do'])){
			
			ini_set('memory_limit','1G');
			
			if ($params['do'] == 'generate'){
				
				// images
				$imagesdir = $GLOBALS['config']['upload_path'];
		
				// compress
				if (file_exists($outfile)){
					unlink($outfile);
				}
				
				$zip = new ZipArchive();
					
				if ($zip->open($outfile, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== TRUE) {
					print('An error occurred');
				}
					
				$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imagesdir));
		    	$files = array_keys(iterator_to_array($iterator, true));
		
		    	foreach ($files as $file) {
		    		if (is_file($file)){
		    			$zip->addFile($file, trim(str_replace(trim($imagesdir, '/\\'), '', $file), '/\\'));
		    		}
		    	}
		
		    	$zip->close();
		    	
		    	// database
		    	include_once($GLOBALS['config']['base_path'].'application/libraries/mysqldump/mysqldump.php');
		    	Export_Database($GLOBALS['config']['database']['hostname'],$GLOBALS['config']['database']['username'],$GLOBALS['config']['database']['password'],$GLOBALS['config']['database']['database'],
		    			$tables, $outfile2);
		    	
		    	$zip = new ZipArchive();
		    	
		    	if ($zip->open($outfile2z, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== TRUE) {
		    		print('An error occurred');
		    	}
		    	
	    		if (is_file($outfile2)){
	    			$zip->addFile($outfile2, 'db.sql');
	    		}
	
		    	$zip->close();
		    	
		    	unlink($outfile2);
		    	
		    	header('Location: '.$GLOBALS['config']['base_url'].'admin/dump/', true, 302);
		    	die();
			
			}
			
			if ($params['do'] == 'cms_dump_resources'){
				
				// uploading resources
				if (!file_exists($GLOBALS['config']['base_path'].'cache/dump')){
					mkdir($GLOBALS['config']['base_path'].'cache/dump');
				}
				
				// collect data
				$this->load->library('upload', array('allowed_types' => 'zip', 'upload_path' => $GLOBALS['config']['base_path'].'cache/dump/', ));

				if ( ! $this->upload->do_upload('file')) {
					print('Problem with file upload. Upload path = '.$this->upload_path);
					print_r($this->upload->display_errors());
					print_r($_FILES);
					die();
				}

				$upload_data = $this->upload->data();

				$filename = $GLOBALS['config']['base_path'].'cache/dump/'.$upload_data['file_name'];
				
 				$zip = new ZipArchive();
					
				$zip->open($filename);
				$zip->extractTo($GLOBALS['config']['upload_path']);
				$zip->close();
				
				$this->rrmdir($GLOBALS['config']['base_path'].'cache/dump');

		    	header('Location: '.$GLOBALS['config']['base_url'].'admin/dump/', true, 302);
		    	die();
			
			}
	    	
			if ($params['do'] == 'cms_dump_database'){
				
				$this->load->model('cms_update_model');
				$this->load->model('cms_slug_model');
				
				// uploading resources
				if (!file_exists($GLOBALS['config']['base_path'].'cache/dump')){
					mkdir($GLOBALS['config']['base_path'].'cache/dump');
				}
				
				// collect data
				$this->load->library('upload', array('allowed_types' => 'zip', 'upload_path' => $GLOBALS['config']['base_path'].'cache/dump/', ));

				if ( ! $this->upload->do_upload('file')) {
					print('Problem with file upload. Upload path = '.$this->upload_path);
					print_r($this->upload->display_errors());
					print_r($_FILES);
					die();
				}

				$upload_data = $this->upload->data();

				$filename = $GLOBALS['config']['base_path'].'cache/dump/'.$upload_data['file_name'];
				
 				$zip = new ZipArchive();
					
				$zip->open($filename);
				$zip->extractTo($GLOBALS['config']['base_path'].'cache/dump/');
				$zip->close();
				
				$sqlfile = $GLOBALS['config']['base_path'].'cache/dump/db.sql';
				
				// rename old tables
				foreach($tables as $table){
					
					$sql = 'drop table if exists '.$table.'_bu';
					$this->cms_update_model->run_sql($sql);
					
					$sql = 'RENAME TABLE `'.$table.'` TO `'.$table.'_bu`';
					$this->cms_update_model->run_sql($sql);
					
				}
					
				// import sql
				
				// Temporary variable, used to store current query
				$templine = '';
				
				// Read in entire file
				$lines = file ( $sqlfile );
				// Loop through each line
				foreach ( $lines as $line ) {
					
					// Skip it if it's a comment
					if (substr ( $line, 0, 2 ) == '--' || $line == '')
						continue;
						
					// Add this line to the current segment
					$templine .= $line;
					// If it has a semicolon at the end, it's the end of the query
					if (substr ( trim ( $line ), - 1, 1 ) == ';') {

						$this->cms_update_model->run_sql($templine);
						$templine = '';

					}
					
				}

				$this->rrmdir($GLOBALS['config']['base_path'].'cache/dump');
				
				// update slugs
				$this->cms_slug_model->_regenerate_cache();
				$this->cms_slug_model->_regenerate_sitemap();
				

		    	header('Location: '.$GLOBALS['config']['base_url'].'admin/dump/', true, 302);
		    	die();
			
			}
	    	
		}
		
		if (file_exists($outfile)){
			$params['filemdate'] = date('jS \of F Y H:i', filemtime($outfile));
		} else {
			$params['filemdate'] = '';
		}
		
		if (file_exists($outfile)){
			$params['filemdate2'] = date('jS \of F Y H:i', filemtime($outfile2z));
		} else {
			$params['filemdate2'] = '';
		}
		
		return $params;
    	
	}
	
}
