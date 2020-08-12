<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class user_model extends Model {
	
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
	
	function set_user_password($user_id, $password){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->cms_page_panel_model->update_cms_page_panel($user_id, [
				'password' => sha1((!empty($GLOBALS['settings']['salt']) ? $GLOBALS['settings']['salt'] : 'cms').$password)
		]);

	}
	
	function get_current(){

		if (empty($_SESSION['user'])){
			return false;
		}
		
		$return = $this->get_user($_SESSION['user']['cms_page_panel_id']);
		
		$return['user_id'] = $return['cms_page_panel_id'];
		
		return $return;
		
	}
	
	function get_user($user_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $this->cms_page_panel_model->get_cms_page_panel($user_id);
		
		if (!empty($return['cms_page_panel_id'])){
			
			$return['user_id'] = $return['cms_page_panel_id'];
			
			if (empty($return['_panel_heading'])){
				$c = &get_instance();
				$return['_panel_heading'] = $c->run_panel_method('user/user', 'panel_heading', $return);
			}
			
			return $return;
		
		} else {
			
			return [];
		
		}
		
	}
	
	function get_users(){
	
		$this->load->model('cms/cms_page_panel_model');
		return $this->cms_page_panel_model->get_list('user/user');
	
	}
	
	function get_users_by_group($usergroup_id){
		
		$this->load->model('cms/cms_page_panel_model');
		$users = $this->cms_page_panel_model->get_list('user/user');
		
		foreach($users as $user_id => $user){
			
			$delete = true;
			foreach($user['usergroups'] as $usergroup){
				if($usergroup['usergroup_id'] == $usergroup_id){
					$delete = false;
				}
			}
			
			if ($delete){
				unset($users[$user_id]);
			}
			
		}
		
		return $users;
		
	}

	function get_current_tempuser(){
		
		$user = [];
	
		// check if in file
		$filename = $GLOBALS['config']['base_path'].'cache/user_tempuser.json';
		if (file_exists($filename)){
			$tempusers = json_decode(file_get_contents($filename), true);
		} else {
			$tempusers = [];
		}
			
		$token = $this->input->get('token');
			
		foreach($tempusers as $key => $row){
				
			if ($token == $row['token'] && (time() - $row['time']) < 80000){
					
				$user = $this->user_model->get_user($row['user_id']);
					
			}
				
			if((time() - $row['time']) >= 100000) {
					
				unset($tempusers[$key]);
					
			}
				
		}
		
		return $user;
	
	}

}
