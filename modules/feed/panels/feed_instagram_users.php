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
        	
        	$this->load->model('cms/cms_page_panel_model');
        	
        	$data = $this->input->get('data');
        	$data = json_decode($data, true);

        	// check if exists
        	$existing = $this->cms_page_panel_model->get_cms_page_panels_by([
        			'panel_name' => 'feed/feed_instagram_user', 
        			'user_id' => $data['user_id'], ]);

        	if (count($existing)){
        		$this->cms_page_panel_model->delete_cms_page_panel($existing[0]['cms_page_panel_id']);
        	}

        	$this->cms_page_panel_model->create_cms_page_panel([
       				'page_id' => 0, 
        			'show' => 1, 
        			'user_id' => $data['user_id'], 
        			'panel_name' => 'feed/feed_instagram_user',
        			'access_token' => $data['access_token'],
        	]);
        	
        	header('Location: '.$GLOBALS['config']['base_url'].'feed/instagram_users/');
        	
        	die();
        	
        }
        
        $do = $this->input->post('do');
        
        if($do == 'feed_instagram_remove'){
        	
        	$cms_page_panel_id = $this->input->post('cms_page_panel_id');
        
        	$this->load->model('cms/cms_page_panel_model');
        	
        	$this->cms_page_panel_model->delete_cms_page_panel($cms_page_panel_id);
        	
        	print('{"result":""}');
        	
        	die();
        
        }
        
	}
	
	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_image_model');
		
  		$params['users'] = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'feed/feed_instagram_user']);
  		
  		foreach($params['users'] as $key => $user){
  			if (empty($user['username'])){

				$url = 'https://graph.instagram.com/'.$user['user_id'].'?fields=username%2Caccount_type%2Cmedia&access_token='.$user['access_token'];
  				$data = json_decode(file_get_contents($url), true);

				$profile_picture = '';
				$content_name = '';
  				
				/*
				$url = 'https://www.instagram.com/'.$data['username'];
				$content = file_get_contents($url);

				if (!empty(explode('og:image" content="', $content)[1])){
					$content_image = explode('og:image" content="', $content)[1];
					$content_image = explode('" />', $content)[0];
					$profile_picture = $this->cms_image_model->scrape_image($content_image, 'instagram', 'feed');
					
					$content_name = explode('og:title" content="', $content)[1];
					$content_name = explode(' (@', $content)[0];
				}
				*/
				
  				$this->cms_page_panel_model->update_cms_page_panel($user['cms_page_panel_id'],[
  						'profile_picture' => $profile_picture,
  						'full_name' => $content_name,
  						'username' => $data['username'],
  				]);

  				$params['users'][$key] = $this->cms_page_panel_model->get_cms_page_panel($user['cms_page_panel_id']);
  				
  			}
  		}

  		return $params;
	
	}

}
