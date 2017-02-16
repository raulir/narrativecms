<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_user_settings extends MY_Controller{
	
	function __construct(){
		
        parent::__construct();        
		
        // check if user
        if(empty($_SESSION['cms_user']['cms_user_id'])){
        	header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
        	exit();
        }
        
	}
		
	function panel_action($params){
				
	}
	
	function panel_params($params){
		
		$this->load->model('cms_user_model');
		
		// get superuser if present
		$params['config_file'] = str_replace($GLOBALS['config']['base_path'], '', $GLOBALS['config']['config_file']);
		
		$params['users'] = $this->cms_user_model->get_cms_users();
		
		$params['users'][] = $this->cms_user_model->new_cms_user();
		
		foreach($params['users'] as &$user){
			$user['rights'] = explode(',', $user['access']);
		}
		
		// get possible rights
		$params['right_names'] = $this->cms_user_model->get_cms_user_all_access_keys();
		
		return $params;
		
	}

}
