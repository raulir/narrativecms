<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_file extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->scss[] = ['script' => 'modules/cms/css/cms_input.scss', ];
	
	}
	
	function panel_action($params){
		
		if (!empty($params['do']) && $params['do'] == 'cms_file_rename'){
			
			$this->load->model('cms_file_model');
			
			$temp = explode('/', $params['old_name']); 
			
			$full_filename = array_pop($temp);
			
			$_extension = explode('.', $full_filename);
			$extension = array_pop($_extension);
			$filename = substr($full_filename, 0, -(strlen('.'.$extension)));
			
			$new_short_name = $params['new_name'].'.'.$extension;
			$new_short_name = $this->cms_file_model->sanitize_filename($new_short_name);
			
			$new_filename = str_replace($filename.'.'.$extension, $new_short_name, $params['old_name']);
			
			// update database
			$this->cms_file_model->rename_file($params['old_name'], $new_filename, $new_short_name);
			
			$params['new_filename'] = $new_filename;
			$params['new_short_name'] = $new_short_name;
				
			// rename real file
			rename($GLOBALS['config']['upload_path'].$params['old_name'], $GLOBALS['config']['upload_path'].$new_filename);
			
			// update all references
			// select from params
			
			// check if file input
			
			
			// invalidate caches
			
		}
		
		return $params;
		
	}

	function panel_params($params){

		if (empty($params['name_clean'])) {
			$params['name_clean'] = $params['name'];
		}

		return $params;

	}
	
}
