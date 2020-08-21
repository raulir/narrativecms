<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class loginlink extends CI_Controller{
	
	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		
	}
	
	function panel_action($params){
		
		$do = $this->input->post('do');

		if ($do == 'create'){
			
			$this->load->model('user/user_model');
			$filename = $GLOBALS['config']['base_path'].'cache/user_reminders.json';
			
			$user_id = $this->input->post('user_id');
			$user = $this->user_model->get_user($user_id);
			
			$url = $this->input->post('url');
			
			$token = sha1(mt_rand(0, mt_getrandmax()));
			
			if (file_exists($filename)){
				$reminders = json_decode(file_get_contents($filename), true);
			} else {
				$reminders = [];
			}
			
			$url = $url . $GLOBALS['config']['base_url'] . 'reminder/?token=' . $token;
			
			$reminders[$token] = [
					'token' => $token,
					'username' => $user['username'],
					'time' => time(),
					'autofill' => 1
			];
			
			file_put_contents($filename, json_encode($reminders, JSON_PRETTY_PRINT));
			
			return ['link' => $url];
			
		}

		return $params;
		
	}

}
