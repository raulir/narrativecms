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
	
	function panel_params($params){
		
		$outfile = $GLOBALS['config']['base_path'].'cache/_dump.zip';
		$outfile2 = $GLOBALS['config']['base_path'].'cache/_database.zip';
		
		if (!empty($params['do'])){
			
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
//	    			var_dump(str_replace(trim($imagesdir, '/\\'), '', $file));
	    			$zip->addFile($file, trim(str_replace(trim($imagesdir, '/\\'), '', $file), '/\\'));
	    		}
	    	}
	
	    	$zip->close();
	    	
	    	// database
	    	
	    	exec('mysqldump -u '..' -p my_database > my_database_dump.sql");
	    	
		}
		
		$params['filemdate'] = date('jS \of F Y', filemtime($outfile));
		$params['filemdate2'] = date('jS \of F Y', filemtime($outfile2));
		
		return $params;
    	
	}
	
}
