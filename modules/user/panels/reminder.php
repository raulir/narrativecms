<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class reminder extends CI_Controller{
	
	function panel_action($params){
		
		$this->load->model('user/user_model');
		
		$filename = $GLOBALS['config']['base_path'].'cache/user_reminders.json';
		
		$do = $this->input->post('do');

		if ($do == 'remind'){
			 
			$username = $this->input->post('username');
			$url = $this->input->post('url');
			
			if (empty($username)){
				$return['error'] = 'bad_username';
				return $return;
			}
			
			$user = $this->user_model->get_user_by_username($username);

			if (empty($user)){
				$user = $this->user_model->get_user_by_email($username);
			}

			if (!(empty($user) || !$user['show'])){
				
				// create a new reminder
				
				$token = sha1(mt_rand(0, mt_getrandmax()));
				
				if (file_exists($filename)){
					$reminders = json_decode(file_get_contents($filename), true);
				} else {
					$reminders = [];
				}
				
				$reminders[$token] = [
						'token' => $token,
						'username' => $username,
						'time' => time(),
				];
				
				file_put_contents($filename, json_encode($reminders, JSON_PRETTY_PRINT));
				
				$title = trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), $GLOBALS['config']['site_title_delimiter'].' ');
				$title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').$title;
				
				$content = $url.'?token='.$token;
				
				mail($user['email'], 'Password reminder from '.$title, $content);

			}
			
		}
		
		if ($do == 'save'){
			 
			$username = $this->input->post('username');
			$token = $this->input->post('token');
			$password = $this->input->post('password');
			$password2 = $this->input->post('password2');
			
			if ($password !== $password2){
				$return['error'] = 'passwords_mismatch';
				return $return;
			}
			
			if (strlen($password) < 5){
				$return['error'] = 'bad_save';
				return $return;
			}
				
			$user = $this->user_model->get_user_by_username($username);

			if (empty($user)){
				$user = $this->user_model->get_user_by_email($username);
			}

			if (!(empty($user) || !$user['show'])){
				
				// check if in file
				if (file_exists($filename)){
					$reminders = json_decode(file_get_contents($filename), true);
				} else {
					$reminders = [];
				}
				
				foreach($reminders as $key => $row){
					
					if ($token == $row['token'] && $username == $row['username'] && (time() - $row['time']) < 1800){
						
						$this->user_model->set_user_password($user['cms_page_panel_id'], $password);
						unset($reminders[$key]);
						
					} 
					
					if((time() - $row['time']) >= 1800) {
						
						unset($reminders[$key]);
						
					}
					
				}

				file_put_contents($filename, json_encode($reminders, JSON_PRETTY_PRINT));
				
				$title = trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), $GLOBALS['config']['site_title_delimiter'].' ');
				
				mail(
						$user['email'],
						(!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').
						'Password update at '.$title,
						'Password associated with this email updated.',
						'From: Caroline Groves <noreply@bytecrackers.com>' . "\r\n" .
						'Sender: Caroline Groves <enquiries@carolinegroves.com>' . "\r\n" .
						'Reply-To: Caroline Groves <enquiries@carolinegroves.com>' . "\r\n");

			}
			
		}
		
		// if token in url, show new password setting
		$token = $this->input->get('token');
		
		$params['success'] = 0;
		$params['autofill'] = '';
		if (!empty($token)){
			
			if (file_exists($filename)){
				$reminders = json_decode(file_get_contents($filename), true);
			} else {
				$reminders = [];
			}
			
			$params['timeout'] = true;
			foreach($reminders as $key => $item){
				
				if ($item['token'] == $token && (time() - $item['time']) < 100000){

					$params['success'] = 1;
					$params['token'] = $token;
					$params['timeout'] = false;
					
					$params['autofill'] = !empty($item['autofill']) ? $item['username'] : '';
					
				}
				
			}
			
		}

		return $params;
		
	}

}
