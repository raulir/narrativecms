<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed_instagram_users extends CI_Controller{
	
	function __construct(){
		
        parent::__construct();        
		
        // check if user
        if(empty($_SESSION['cms_user']['cms_user_id'])){
        	header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
        	exit();
        }
        
	}

	function panel_action($params){

		$do = $this->input->get('do');
		
        if ($do == 'feed_instagram_auth'){
        	
        	$data = $this->input->get('data');
        	$data = json_decode($data, true);
        	
        	$data['user']['access_token'] = $data['access_token'];
        	
        	$this->load->model('cms_page_panel_model');
        	
        	// check if exists
        	$existing = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => ['feed/feed_instagram_user', 'feed_instagram_user'], 'username' => $data['user']['username'], ]);

        	if (count($existing)){
        		$this->cms_page_panel_model->delete_cms_page_panel($existing[0]['cms_page_panel_id']);
        	}
        	
        	$this->cms_page_panel_model->create_cms_page_panel(array_merge([
       				'page_id' => 0, 'show' => 1, 'title' => $data['user']['username'], 'panel_name' => 'feed/feed_instagram_user', ], $data['user']));
        	
        	header('Location: '.$GLOBALS['config']['base_url'].'feed/instagram_users/');
        	die();
        	
        }
        
        $do = $this->input->post('do');
        
        if($do == 'feed_instagram_remove'){
        	
        	$cms_page_panel_id = $this->input->post('cms_page_panel_id');
        
        	$this->load->model('cms_page_panel_model');
        	
        	$this->cms_page_panel_model->delete_cms_page_panel($cms_page_panel_id);
        	
        	print('{}');
        	
        	die();
        
        }
        
	}
	
	function panel_params($params){

		$this->load->model('cms_page_panel_model');
		
  		$params['users'] = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => ['feed/feed_instagram_user', 'feed_instagram_user'], ));

  		return $params;
	
	}

}
