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
	
	function makesize($size){
	
		if ($size < 512){
	
			return $size.' B';
	
		}
			
		$size = $size / 1024;
	
		if ($size < 100){
			return round($size, 1).' kB';
		} else if ($size < 512){
			return round($size).' kB';
		}
	
		$size = $size / 1024;
	
		if ($size < 100){
			return round($size, 1).' MB';
		} else if ($size < 512){
			return round($size).' MB';
		}
	
		$size = $size / 1024;
	
		return round($size, 1).' GB';
	
	}
	
	function add_month_to_zip($zip, $month_string){
		
		$imagesdir = $GLOBALS['config']['upload_path'].'/'.$month_string;
		
		if (is_dir($imagesdir)){
		
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imagesdir));
			$files = array_keys(iterator_to_array($iterator, true));
				
			foreach ($files as $file) {
				if (is_file($file)){
						
					$new_name = $month_string.'/'.str_replace('\\', '/', trim(str_replace(trim($imagesdir, '/\\'), '', $file), '/\\'));
					$zip->addFile($file, $new_name);
			
				}
			}
		
		}

	}
	
	function panel_action($params){
		
		$this->load->model('cms_update_model');
		
		$tables = ['block', 'cms_page_panel', 'cms_file', 'cms_image', 'cms_keyword', 'cms_page', 'cms_page_panel_param', 'cms_search_cache', 'cms_slug', 'cms_text', 'cms_user', 'menu_item', ];
		
		if (!empty($params['do'])){
				
			ini_set('memory_limit','1G');
			set_time_limit(300);
				
			if ($params['do'] == 'generate'){
				
				include_once($GLOBALS['config']['base_path'].'system/vendor/mysqldump/mysqldump.php');
				
				// images
				$imagesdir = $GLOBALS['config']['upload_path'];
				$sql_temp = $GLOBALS['config']['base_path'].'cache/_database.sql';
				if (file_exists($sql_temp)){
					unlink($sql_temp);
				}
				
				if (empty($params['what'])){
					
					// generate all
					$outfile = $GLOBALS['config']['base_path'].'cache/_dump.zip';
					
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
					
							$new_name = str_replace('\\', '/', trim(str_replace(trim($imagesdir, '/\\'), '', $file), '/\\'));
							$zip->addFile($file, $new_name);

						}
					}
					
					// database
					Export_Database($GLOBALS['config']['database']['hostname'],$GLOBALS['config']['database']['username'],$GLOBALS['config']['database']['password'],$GLOBALS['config']['database']['database'],
							$tables, $sql_temp);
						
					if (is_file($sql_temp)){
						$zip->addFile($sql_temp, 'db.sql');
					}
					
					$zip->close();
						
					unlink($sql_temp);
						
					header('Location: '.$GLOBALS['config']['base_url'].'admin/dump/', true, 302);
					die();
					
				} else if ($params['what'] == '2month'){
					
					// 2 months + sql
					
					$outfile = $GLOBALS['config']['base_path'].'cache/_dump_2.zip';
					
					if (file_exists($outfile)){
						unlink($outfile);
					}
					
					$zip = new ZipArchive();
						
					if ($zip->open($outfile, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== TRUE) {
						print('An error occurred');
					}
					
					// add images
					$this->add_month_to_zip($zip, date('Y').'/'.date('m'));
					$this->add_month_to_zip($zip, date('Y', strtotime('first day of last month')).'/'.date('m', strtotime('first day of last month')));
					
					// add sql file
					Export_Database($GLOBALS['config']['database']['hostname'], $GLOBALS['config']['database']['username'], $GLOBALS['config']['database']['password'],
							$GLOBALS['config']['database']['database'],	$tables, $sql_temp);
						
					if (is_file($sql_temp)){
						$zip->addFile($sql_temp, 'db.sql');
					}
						
					$zip->close();
						
					unlink($sql_temp);
						
					header('Location: '.$GLOBALS['config']['base_url'].'admin/dump/', true, 302);
					die();
					
				} else if ($params['what'] == 'database'){
					
					// 2 months + sql
					
					$outfile = $GLOBALS['config']['base_path'].'cache/_dump_db.zip';
					
					if (file_exists($outfile)){
						unlink($outfile);
					}
					
					$zip = new ZipArchive();
						
					if ($zip->open($outfile, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== TRUE) {
						print('An error occurred');
					}
					
					// add sql file
					Export_Database($GLOBALS['config']['database']['hostname'], $GLOBALS['config']['database']['username'], $GLOBALS['config']['database']['password'],
							$GLOBALS['config']['database']['database'],	$tables, $sql_temp);
						
					if (is_file($sql_temp)){
						$zip->addFile($sql_temp, 'db.sql');
					}
						
					$zip->close();
						
					unlink($sql_temp);
						
					header('Location: '.$GLOBALS['config']['base_url'].'admin/dump/', true, 302);
					die();
					
				}
				
			}
				
			if ($params['do'] == 'cms_dump_upload'){
		
				$sqlfile = $GLOBALS['config']['upload_path'].'db.sql';
				if (file_exists($sqlfile)){
					unlink($sqlfile);
				}
				
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
				
				// check if there is db.sql in upload directory
				if (file_exists($sqlfile)){
					
					// rename old tables
					foreach($tables as $table){
							
						$sql = 'drop table if exists '.$table.'_bu';
						$this->cms_update_model->run_sql($sql);
						
						$sql = "select 1 from ".$table." limit 1";
						$query = $this->db->query($sql);
						
						if ($query && $query->num_rows()){
							$sql = 'RENAME TABLE `'.$table.'` TO `'.$table.'_bu`';
							$this->cms_update_model->run_sql($sql);
						}
						
					}
						
					// import sql
			
					// Temporary variable, used to store current query
					$templine = '';
			
					// Read in entire file
					$lines = file ( $sqlfile );
					// Loop through each line
					foreach ( $lines as $line ) {
							
						// Skip it if it's a comment
						if (substr ( $line, 0, 2 ) == '--' || $line == '') continue;

						// Add this line to the current segment
						$templine .= $line;
						// If it has a semicolon at the end, it's the end of the query
						if (substr ( trim ( $line ), - 1, 1 ) == ';') {
		
							$this->cms_update_model->run_sql($templine);
							$templine = '';
		
						}

					}
					
					unlink($sqlfile);
				
				}

				header('Location: '.$GLOBALS['config']['base_url'].'admin/dump/', true, 302);
				die();
					
			}
		
		}
		
		return $params;
		
	}
	
	function panel_params($params){
		
		$params['files'] = [
				[
						'filename' => '_dump_2.zip',
						'heading' => 'Database and 2 months of files',
						'trigger' => '2month',
				],
				[
						'filename' => '_dump_db.zip',
						'heading' => 'Database only',
						'trigger' => 'database',
				],
				[
						'filename' => '_dump.zip',
						'heading' => 'Database and all files',
						'trigger' => '',
				],
		];
		
		foreach($params['files'] as $key => $file){
			
			$filename = $GLOBALS['config']['base_path'].'cache/'.$file['filename'];
			if (file_exists($filename)){
				$params['files'][$key]['filemtime'] = date('j M Y H:i', filemtime($filename));
				$params['files'][$key]['size'] = $this->makesize(filesize($filename));
			} else {
				$params['files'][$key]['filemtime'] = '';
				$params['files'][$key]['size'] = '';
			}
			
		}
		
		return $params;
    	
	}
	
}
