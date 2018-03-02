<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Files extends MY_Controller {
	
    function download($file_id){

    	// get panels on page by slug
    	$this->load->model('cms_file_model');
    	$file = $this->cms_file_model->get_file($file_id);
    	
    	if ($file_id && !empty($file['cms_file_id']) && !empty($_SESSION['user']['user_id']) && 
    			($file['user_id'] == 0 || $file['user_id'] == $_SESSION['user']['user_id'])){
    		
	    	header('Content-Disposition: attachment; filename="'.$file['name'].'"');
	    	readfile($GLOBALS['config']['upload_path'].$file['filename']);
    		
    		exit();
    		
    	}

    }
    
    function get($filename){

    	// get panels on page by slug
    	$this->load->model('cms_file_model');
    	$file = $this->cms_file_model->get_cms_file_by_filename(str_replace('__', '/', $filename));
    	
    	if ($filename && !empty($file['cms_file_id']) &&  
    			($file['cms_user_id'] == 0 || (!empty($_SESSION['user']['user_id']) && $file['cms_user_id'] == $_SESSION['user']['user_id'])) && 
    			file_exists($GLOBALS['config']['upload_path'].$file['filename'])){
    		
	    	header('Content-Disposition: attachment; filename="'.$file['name'].'"');
	    	
	    	if (pathinfo($file['name'], PATHINFO_EXTENSION) == 'pdf'){
				header('Content-Type: application/pdf');
	    	} else if (pathinfo($file['name'], PATHINFO_EXTENSION) == 'png'){
				header('Content-Type: image/png');
	    	} else if (pathinfo($file['name'], PATHINFO_EXTENSION) == 'gif'){
				header('Content-Type: image/gif');
	    	} else {
	    		header('Content-Type: application/x-download');
	    	}
	    	
	    	header('Cache-Control: private, max-age=0, must-revalidate');
	    	header('Pragma: public');
	    	
	    	readfile($GLOBALS['config']['upload_path'].$file['filename']);
    		
    		exit();
    		
    	} else {

    		print('Problem accessing file!');

		}

    }
    
}
