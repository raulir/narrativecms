<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class user_model extends CI_Controller {
	
	function create_user($data){
		
		$return = [];
		
		$this->load->model('cms/cms_page_panel_model');

		// check if email exists
		$check_user = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'email' => $data['email'], ]);
		$check_user = reset($check_user);
		 
		if ($check_user !== false){
		
			$return['error'] = 'emailexists';
			return $return;
		
		}
		 
		// check if username exists
		if (!empty($data['username'])){
		
			$check_user = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'username' => $data['username'], ]);
			$check_user = reset($check_user);
			
			if ($check_user !== false){
				 
				$return['error'] = 'usernameexists';
				return $return;
				 
			}
			
			if (strlen($data['username']) < 3) {
			
				$return['error'] = 'usernamelength';
				return $return;
				
			}
		
		} else {
			$data['username'] = '';
		}
		
		if (!stristr($data['email'], '@') || !stristr($data['email'], '.')) {
				
			$return['error'] = 'bademail';
			return $return;
		
		}
		

		$user = [
				'panel_name' => 'user/user',
				'show' => 1,
				'sort' => 'first',
				'username' => $data['username'],
				'first_name' => $data['first_name'],
				'last_name' => $data['last_name'],
				'email' => $data['email'],
				'phone' => $data['phone'],
				'password' => sha1((!empty($GLOBALS['settings']['salt']) ? $GLOBALS['settings']['salt'] : 'cms').$data['password']),
		];
		 
		$user_id = $this->cms_page_panel_model->create_cms_page_panel($user);
		$user['user_id'] = $user_id;

		return ['data' => $user];
	
	}
	
	function get_user_by_username($username){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'username' => $username, 'show' => 1, ]);
		
		if (!empty($return[0])){
			return $return[0];
		} else {
			return [];
		}

	}

	function get_user_by_email($email){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'email' => $email, 'show' => 1, ]);
		
		if (!empty($return[0])){
			return $return[0];
		} else {
			return [];
		}

	}

}