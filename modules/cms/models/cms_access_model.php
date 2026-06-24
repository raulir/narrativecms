<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_access_model extends Model {
	
	function get_panel_required_access($panel_name){
		
		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		
		if (empty($config['access']) || $config['access'] === '*'){
			return '';
		}
		
		return $config['access'];
		
	}
	
	function parse_access_keys($subject){
		
		if (empty($subject) || !is_array($subject)){
			return [];
		}
		
		if (!empty($subject['access_keys']) && is_array($subject['access_keys'])){
			return array_values(array_filter(array_map('trim', $subject['access_keys'])));
		}
		
		if (!empty($subject['access']) && is_array($subject['access'])){
			
			$keys = [];
			
			foreach ($subject['access'] as $row){
				
				if (is_array($row) && !empty($row['key'])){
					$keys[] = trim($row['key']);
				} else if (is_string($row)){
					$keys[] = trim($row);
				}
				
			}
			
			return array_values(array_filter($keys));
			
		}
		
		return [];
		
	}
	
	function get_session_access_keys(){
		
		if (!empty($_SESSION['access_keys']) && is_array($_SESSION['access_keys'])){
			return array_values(array_filter(array_map('trim', $_SESSION['access_keys'])));
		}
		
		if (!empty($_SESSION['user'])){
			return $this->parse_access_keys($_SESSION['user']);
		}
		
		return [];
		
	}
	
	function _access_pattern($key){
		
		return '/'.str_replace('\*', '.*?', preg_quote(trim($key), '/')).'/';
		
	}
	
	function _access_key_matches($user_key, $required){
		
		$user_key = trim($user_key);
		$required = trim($required);
		
		if ($user_key === '' || $required === ''){
			return false;
		}
		
		if ($user_key === '*' || $required === '*'){
			return true;
		}
		
		return preg_match($this->_access_pattern($user_key), $required)
				|| preg_match($this->_access_pattern($required), $user_key);
		
	}
	
	function user_has_access($required_access, $user_keys = null){
		
		if ($required_access === '' || $required_access === '*'){
			return true;
		}
		
		if ($user_keys === null){
			$user_keys = $this->get_session_access_keys();
		}
		
		if (empty($user_keys)){
			return false;
		}
		
		foreach ($user_keys as $access){
			
			if ($this->_access_key_matches($access, $required_access)){
				return true;
			}
			
		}
		
		return false;
		
	}
	
	function get_cache_access_hash($panel_name){
		
		$required = $this->get_panel_required_access($panel_name);
		
		if ($required === ''){
			return '';
		}
		
		$keys = $this->get_session_access_keys();
		
		if (empty($keys)){
			return 'guest';
		}
		
		sort($keys);
		
		return substr(md5(implode('|', $keys)), 0, 8);
		
	}
	
	function keys_to_repeater($keys){
		
		$repeater = [];
		
		foreach ($keys as $key){
			
			$key = trim($key);
			
			if ($key === ''){
				continue;
			}
			
			$repeater[] = ['key' => $key];
			
		}
		
		return $repeater;
		
	}
	
	function _merge_access_key_lists($existing, $add){
		
		$merged = $existing;
		
		foreach ($add as $key){
			
			$key = trim($key);
			
			if ($key === '' || in_array($key, $merged, true)){
				continue;
			}
			
			$merged[] = $key;
			
		}
		
		return $merged;
		
	}
	
	function get_module_login_access_keys(){
		
		$this->load->model('cms/cms_module_model');
		
		$keys = [];
		
		foreach ($GLOBALS['config']['modules'] as $module){
			
			$config = $this->cms_module_model->get_module_config($module);
			
			if (empty($config['login_access']) || !is_array($config['login_access'])){
				continue;
			}
			
			foreach ($config['login_access'] as $row){
				
				if (!empty($row['access'])){
					$keys[] = trim($row['access']);
				}
				
			}
			
		}
		
		return array_values(array_filter(array_unique($keys)));
		
	}
	
	function get_default_access_for_new_user(){
		
		if (!in_array('user', $GLOBALS['config']['modules'])){
			return [];
		}
		
		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('user/user_settings');
		
		if (empty($settings['default_access'])){
			return [];
		}
		
		return $this->parse_access_keys(['access' => $settings['default_access']]);
		
	}
	
	function _persist_user_access_keys($user_id, $keys){
		
		if (!in_array('user', $GLOBALS['config']['modules']) || empty($user_id)){
			return [];
		}
		
		$this->load->model('cms/cms_page_panel_model');
		$user = $this->cms_page_panel_model->get_cms_page_panel($user_id);
		
		if (empty($user['cms_page_panel_id'])){
			return [];
		}
		
		$existing = $this->parse_access_keys($user);
		$merged = $this->_merge_access_key_lists($existing, $keys);
		
		if ($merged === $existing){
			return $merged;
		}
		
		$this->cms_page_panel_model->update_cms_page_panel($user_id, [
				'access' => $this->keys_to_repeater($merged),
		]);
		
		return $merged;
		
	}
	
	function _clear_user_session(){
		
		unset($_SESSION['user']);
		unset($_SESSION['access_keys']);
		
	}
	
	function refresh_user_session($user){
		
		if (empty($user) || !is_array($user)){
			return;
		}
		
		$user_id = $user['cms_page_panel_id'] ?? $user['user_id'] ?? 0;
		
		if ($user_id && in_array('user', $GLOBALS['config']['modules'])){
			
			$login_keys = $this->get_module_login_access_keys();
			$merged = $this->_persist_user_access_keys($user_id, $login_keys);
			
			$this->load->model('user/user_model');
			$user = $this->user_model->get_user($user_id);
			
			if (!empty($merged)){
				$user['access_keys'] = $merged;
			}
			
		}
		
		$user['access_keys'] = $this->parse_access_keys($user);
		$_SESSION['user'] = $user;
		
	}
	
	function _get_login_redirect(){
		
		if (!in_array('user', $GLOBALS['config']['modules'])){
			return [
				'url' => $GLOBALS['config']['base_url'],
				'text' => 'Login',
			];
		}
		
		$this->load->model('user/user_model');
		
		return $this->user_model->get_login_redirect();
		
	}
	
	function parse_comma_access_keys($access_string){
		
		if (!is_string($access_string) || trim($access_string) === ''){
			return [];
		}
		
		$keys = array_map('trim', explode(',', $access_string));
		
		return array_values(array_filter($keys));
		
	}
	
	function user_has_page_access($page_access, $user_keys = null){
		
		$required_keys = $this->parse_comma_access_keys($page_access);
		
		if (empty($required_keys)){
			return true;
		}
		
		foreach ($required_keys as $required){
			
			if ($this->user_has_access($required, $user_keys)){
				return true;
			}
			
		}
		
		return false;
		
	}
	
	function get_access_denied_inline_html(){
		
		return _html_error('Access denied', 0, ['location' => 'Access denied', 'silent' => true]);
		
	}
	
	function _reject_access($params = []){
		
		$was_logged_in = !empty($_SESSION['user']);
		
		if ($was_logged_in){
			$this->_clear_user_session();
		}
		
		$login_redirect = $this->_get_login_redirect();
		
		if (!empty($params['no_html'])){
			
			header('Content-Type: application/json');
			print(json_encode([
				'result' => [],
				'error' => [
					'message' => 'access_denied',
					'login_url' => $login_redirect['url'],
					'login_text' => $login_redirect['text'],
				],
			]));
			exit();
			
		}
		
		header('Location: '.$login_redirect['url'], true, 302);
		exit();
		
	}
	
	function enforce_page_access($page, $params = []){
		
		if (empty($page['access']) || trim($page['access']) === ''){
			return;
		}
		
		if ($this->user_has_page_access($page['access'])){
			return;
		}
		
		$this->_reject_access($params);
		
	}
	
	function enforce_panel_access($panel_name, $params = []){
		
		$required = $this->get_panel_required_access($panel_name);
		
		if ($required === ''){
			return;
		}
		
		if ($this->user_has_access($required)){
			return;
		}
		
		$this->_reject_access($params);
		
	}
	
}