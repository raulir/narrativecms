<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class user_model extends Model {
	
	function create_user($data){
		
		$return = ['errors' => []];
		
		$this->load->model('cms/cms_page_panel_model');

		if (!empty($data['email'])){
			
			$check_user = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'email' => $data['email'], ]);
			$check_user = reset($check_user);
			 
			if ($check_user !== false){
			
				$return['errors'][] = 'emailexists';
			
			}
			
		}
		 
		// check if username exists
		if (!empty($data['username'])){
		
			$check_user = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'user/user', 'username' => $data['username'], ]);
			$check_user = reset($check_user);
			
			if ($check_user !== false){
				 
				$return['errors'][] = 'usernameexists';
				 
			}
			
			if (strlen($data['username']) < 3) {
			
				$return['errors'][] = 'usernamelength';
				
			}
		
		}
		
		if (!empty($data['email'])){
			
			if (!stristr($data['email'], '@') || !stristr($data['email'], '.')){
					
				$return['errors'][] = 'bademail';
			
			}
			
		}
		
		if (!empty($return['errors'])){
			return $return;
		}

		$this->load->model('cms/cms_access_model');
		$default_access = $this->cms_access_model->get_default_access_for_new_user();
		
		$user = [
				'panel_name' => 'user/user',
				'show' => 1,
				'sort' => 'first',
				'username' => $data['username'],
				'first_name' => $data['first_name'],
				'last_name' => $data['last_name'],
				'email' => $data['email'],
				'phone' => $data['phone'],
				'password' => !empty($data['password']) ? $this->hash_password($data['password']) : '',
				'email_verified' => !empty($data['email_verified']) ? $data['email_verified'] : 'no',
				'image' => '',
				'meta' => json_encode($data['meta'], JSON_PRETTY_PRINT),
		];
		
		if (!empty($default_access)){
			$user['access'] = $this->cms_access_model->keys_to_repeater($default_access);
		}
		 
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
				'password' => $this->hash_password($password)
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

	function _parse_user_meta($user){

		if (empty($user['meta'])){
			return [];
		}

		$meta = cms_json_decode($user['meta'], 'user meta');

		return is_array($meta) ? $meta : [];

	}

	function get_user_meta_value($key, $default = ''){

		$user = $this->get_current();

		if (empty($user)){
			return $default;
		}

		$meta = $this->_parse_user_meta($user);

		return $meta[$key] ?? $default;

	}

	function set_user_meta_value($key, $value){

		$user = $this->get_current();

		if (empty($user)){
			return false;
		}

		$meta = $this->_parse_user_meta($user);
		$meta[$key] = $value;

		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->update_cms_page_panel($user['user_id'], [
				'meta' => json_encode($meta, JSON_PRETTY_PRINT),
		]);

		return true;

	}

	function get_user_naming(){

		$allowed = ['anglo_american', 'german', 'solfege'];
		$naming = $this->get_user_meta_value('naming', 'anglo_american');

		return in_array($naming, $allowed, true) ? $naming : 'anglo_american';

	}
	
	function get_user($user_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$return = $this->cms_page_panel_model->get_cms_page_panel($user_id);
		
		if (!empty($return['cms_page_panel_id'])){
			
			$return['user_id'] = $return['cms_page_panel_id'];

			$config = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
			if (empty($config['show_username'])){
				$return['loginname'] = $return['email'];
			} else {
				$return['loginname'] = $return['username'];
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
				
			if ($token == $row['token'] && (time() - $row['time']) < 604800){
					
				$user = $this->user_model->get_user($row['user_id']);
				if(!empty($row['topic_id'])){
					$_SESSION['userchat_topic_id'] = $row['topic_id'];
				}
					
			}
				
			if((time() - $row['time']) >= 1000000) {
					
				unset($tempusers[$key]);
					
			}
				
		}
		
		return $user;
	
	}
	
	function get_link_settings(){
		
		$settings = $this->get_user_settings();
		
		return [
				'login_link' => $settings['login_link'] ?? [],
				'user_link' => $settings['user_link'] ?? [],
				'logout_link' => $settings['logout_link'] ?? [],
		];
		
	}
	
	function get_login_redirect_url(){
		
		$links = $this->get_link_settings();
		
		if (!empty($links['login_link'])){
			return _l($links['login_link'], false);
		}
		
		return $GLOBALS['config']['base_url'];
		
	}
	
	function get_login_redirect_text(){
		
		return 'Login';
		
	}
	
	function get_login_redirect(){
		
		return [
			'url' => $this->get_login_redirect_url(),
			'text' => $this->get_login_redirect_text(),
		];
		
	}
	
	function get_user_redirect_url(){
		
		$links = $this->get_link_settings();
		
		if (!empty($links['user_link'])){
			return _l($links['user_link'], false);
		}
		
		return $GLOBALS['config']['base_url'];
		
	}
	
	function get_logout_redirect_url(){
		
		$links = $this->get_link_settings();
		
		if (!empty($links['logout_link'])){
			return _l($links['logout_link'], false);
		}
		
		return $GLOBALS['config']['base_url'];
		
	}
	
	function hash_password($password){
		
		return password_hash($password, PASSWORD_DEFAULT);
		
	}
	
	function verify_password($password, $stored_hash){
		
		if (empty($stored_hash)){
			return false;
		}
		
		if (strpos($stored_hash, '$2y$') === 0 || strpos($stored_hash, '$argon2') === 0){
			return password_verify($password, $stored_hash);
		}
		
		$legacy = sha1((!empty($GLOBALS['settings']['salt']) ? $GLOBALS['settings']['salt'] : 'cms').$password);
		
		return hash_equals($legacy, $stored_hash);
		
	}
	
	function upgrade_password_hash($user_id, $password){
		
		$this->set_user_password($user_id, $password);
		
	}
	
	function get_user_settings(){
		
		$this->load->model('cms/cms_page_panel_model');
		
		return $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
		
	}
	
	function email_confirmation_required(){
		
		$settings = $this->get_user_settings();
		
		return !empty($settings['email_confirmation']);
		
	}
	
	function is_email_verified($user){
		
		return empty($user['email_verified']) || $user['email_verified'] === 'yes';
		
	}
	
	function login_allowed($user){
		
		if (!$this->email_confirmation_required()){
			return true;
		}
		
		return $this->is_email_verified($user);
		
	}
	
	function send_email_verification($user_id){
		
		$user = $this->get_user($user_id);
		
		if (empty($user['email'])){
			return false;
		}
		
		$filename = $GLOBALS['config']['base_path'].'cache/user_email_verifications.json';
		
		if (file_exists($filename)){
			$tokens = cms_json_decode(file_get_contents($filename), $filename);
		} else {
			$tokens = [];
		}
		
		$token = sha1(mt_rand(0, mt_getrandmax()).$user_id.time());
		
		$tokens[$token] = [
				'token' => $token,
				'user_id' => $user_id,
				'email' => $user['email'],
				'time' => time(),
		];
		
		file_put_contents($filename, json_encode($tokens, JSON_PRETTY_PRINT));
		
		$verify_url = $GLOBALS['config']['base_site'].$GLOBALS['config']['base_url'].'verify-email/?token='.$token;
		
		$title = trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), $GLOBALS['config']['site_title_delimiter'].' ');
		$title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').$title;
		
		$headers = 'From: '.(!empty($GLOBALS['config']['email']) ? $GLOBALS['config']['email'] : 'noreply@localhost')."\r\n";
		$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
		
		mail(
				$user['email'],
				(!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').
				'Confirm your email for '.$title,
				"Please confirm your email by opening this link:\n\n".$verify_url,
				$headers
		);
		
		return true;
		
	}
	
	function verify_email_token($token){
		
		$filename = $GLOBALS['config']['base_path'].'cache/user_email_verifications.json';
		
		if (!file_exists($filename)){
			return ['error' => 'invalid_token'];
		}
		
		$tokens = cms_json_decode(file_get_contents($filename), $filename);
		
		if (empty($tokens[$token])){
			return ['error' => 'invalid_token'];
		}
		
		$row = $tokens[$token];
		
		if ((time() - $row['time']) >= 604800){
			unset($tokens[$token]);
			file_put_contents($filename, json_encode($tokens, JSON_PRETTY_PRINT));
			return ['error' => 'expired_token'];
		}
		
		$this->load->model('cms/cms_page_panel_model');
		$this->cms_page_panel_model->update_cms_page_panel($row['user_id'], [
				'email_verified' => 'yes',
		]);
		
		unset($tokens[$token]);
		file_put_contents($filename, json_encode($tokens, JSON_PRETTY_PRINT));
		
		return ['user_id' => $row['user_id']];
		
	}

}
