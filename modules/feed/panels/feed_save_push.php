<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed_save_push extends CI_Controller{
	
	function __construct(){
		
        parent::__construct();        
		
        // check if user
        if(empty($_SESSION['cms_user']['cms_user_id'])){
        	header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
        	exit();
        }
        
	}

	function panel_action($params){

		$do = $this->input->post('do');
        if ($do == 'feed_save_push'){
        	
        	$cms_page_panel_id = $this->input->post('cms_page_panel_id');
        	
        	$this->load->model('feed_model');
        	
        	$this->feed_model->refresh_item($cms_page_panel_id);
        	
        }

	}
	
	function panel_params($params){

		$this->load->model('cms_page_panel_model');
		
		$params['show'] = 0;
		
		if ($params['cms_page_panel_id']){
			
	  		$original_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('block_id' => $params['cms_page_panel_id'], ));
	   		
	   		$old_a = $this->cms_page_panel_model->get_cms_page_panels_by(array(
					'panel_name' => ['feed/feed', 'feed'], 
					'source' => $original_a[0]['panel_name'],
					'source_id' => $params['cms_page_panel_id'],
			));
			
			if (!empty($old_a[0])){
				$params['show'] = 1;
			}

		}
		
		return $params;
	
	}
		
	// todo: add check if the list is part of the feed and show button only then

}
