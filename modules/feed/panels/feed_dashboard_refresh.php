<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed_dashboard_refresh extends CI_Controller{
	
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
        if ($do == 'feed_dashboard_refresh'){
        	
        	$this->load->model('feed/feed_model');
        	
        	$params['stats'] = $this->feed_model->refresh_feeds();
        	
        	if (!empty($GLOBALS['feed_error'])){
        		$params['feed_error'] = $GLOBALS['feed_error'];
        	}
        	
        }
        
        return $params;

	}

}
