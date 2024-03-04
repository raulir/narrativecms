<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('get_user_ip')){

	function get_user_ip(){

		if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			$_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
			$_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
		}

		$client = $_SERVER['HTTP_CLIENT_IP'] ?? false;

		$forward = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? false;

		$remote  = $_SERVER['REMOTE_ADDR'];

		if(filter_var($client, FILTER_VALIDATE_IP)) {
			$ip = $client;
		} elseif(filter_var($forward, FILTER_VALIDATE_IP)) {
			$ip = $forward;
		} else {
			$ip = $remote;
		}

		return $ip;

	}

}

class do_send extends CI_Controller {
	
	function panel_action($params){

		$do = $this->input->post('do');
        if ($do == 'send_form'){

			$this->load->model('cms/cms_page_panel_model');
			$this->load->model('cms/cms_page_model');
        	$this->load->model('form/form_model');
        	
        	$cms_page_panel_id = $this->input->post('id');
        	
        	// collect data
        	$data = $this->input->post();
        	
        	if (!empty($cms_page_panel_id)){
				$params = array_merge($params, $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id));
        	}
        		
       		// try to load settings from basic form settings
       		$default_params = $this->cms_page_panel_model->get_cms_page_panel_settings('form/basic');
       		
       		$params = array_merge($default_params, $params);
        	
        	unset($data['do']);
            if (isset($data['panel_id'])){
	        	unset($data['panel_id']);
        	}
        	if (isset($data['no_html'])){
        		unset($data['no_html']);
        	}
        	if (isset($data['cache'])){
        		unset($data['cache']);
        	}
        	
        	$captcha_verified = true;
        	if (isset($data['recaptcha_token']) && empty($GLOBALS['config']['environment'])){
        		
        		$captcha_verified = false;
        		
        		$panel = $this->cms_page_panel_model->get_cms_page_panel($data['id']);
        		
        		// recaptcha check
        		$context = stream_context_create([
					'http' => [
        				'method' => 'POST',
        				'content'=> http_build_query([
        					'secret' => $panel['recaptcha_server_key'],
        					'response' => $data['recaptcha_token'],
        				]),
        			],
        		]);
        		        		
        		@$response = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify', 0, $context), true);
        		
        		if ($response['success'] == 1 && $response['action'] == 'form'){
        			$captcha_verified = true;
        			$data['captcha'] = $response['score'].'|'.$response['challenge_ts'];
        		}

        	}
        	
        	if (!$captcha_verified){
        		$return['result'] = ['error' => $panel['recaptcha_error_message']];
        	}

        	if (isset($data['recaptcha_token'])){
        		unset($data['recaptcha_token']);
        	}

        	if (isset($data['id'])){
        		unset($data['id']);
        	}
        	
        	$data['ip'] = get_user_ip();
        	
        	// page title for email
	        $title_parts = [trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), $GLOBALS['config']['site_title_delimiter'].' ')];
        	// if settings
        	if(!empty($params['cms_page_id'])){
        	
	        	$page = $this->cms_page_model->get_page($params['cms_page_id']);
	        	
	        	if (!empty($page['title'])){
	        		$title_parts[] = $page['title'];
	        	}
	        	
        	}
        	$title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '') . 
        			'New form "'.$params['title'].'" submission on "'.implode(' - ', $title_parts).'"';

        	// send notification
			if(!empty($params['emails']) && count($params['emails'])){
				
				$reply_to = ['email' => '', 'name' => ''];
				if (!empty($GLOBALS['config']['email'])){
					$from = $GLOBALS['config']['email'];
				} else {
					$from = $_SERVER['SERVER_NAME'].'@narrativecms.com';
				}
				
				if (!empty($data['email']) && stristr($data['email'], '@') && stristr($data['email'], '.')){
						
					$reply_to['email'] = $data['email'];
					
					$name = trim($data['name'] ?? (($data['first_name'] ?? '').' '.($data['last_name'] ?? '')));
					
					$reply_to['name'] = $name ?? $data['email'];

				}
				
				$this->form_model->send_contact_request($params['emails'], $data, $title, $from, $reply_to );
			
			}
			
			// autoreply
			if(!empty($params['autoreply'])){
				
				if ($params['confirm']){
					
					$data['confirmation_code'] = md5('CMS'.mt_rand(1000, 9999).$data['email']);
					
					if (!stristr($params['autoreply_text'], '[codeurl]')){
						$params['autoreply_text'] .= "\r\n\r\n".'[codeurl]';
					}
					
					$data['codeurl'] = 'http'.($_SERVER['SERVER_PORT'] == 80 ? '' : 's').'://'.
							$_SERVER['SERVER_NAME'].$GLOBALS['config']['base_url'].$page['slug'].'/?confirmation_code='.$data['confirmation_code'];
				
				}
				
				$this->form_model->send_autoreply($data, $params);
			
			}
			
			$this->form_model->create_form_data($cms_page_panel_id, !empty($data['email']) ? $data['email'] : '', $data);
			
			$return = [];

			// add to cm or mailchimp
        	if (!empty($params['add_mailchimp']) && !empty($data['email']) && !empty($params['mailchimp_api_key']) && !empty($params['mailchimp_list_id'])){
				$result = $this->form_model->create_mailchimp_subscriber($data, $params);
			}
			
        	if (!empty($params['add_cm']) && !empty($data['email']) && !empty($params['cm_api_key']) && 
					!empty($params['cm_api_url']) && !empty($params['cm_list_id'])){
				$result = $this->form_model->create_cm_subscriber($data, $params);
			}
			
			if (!empty($params['add_sendgrid'])){
				
				if (empty($params['sendgrid_api_key']) && !empty($GLOBALS['config']['sendgrid_apikey'])){
					$params['sendgrid_api_key'] = $GLOBALS['config']['sendgrid_apikey'];
				}
				
				if(!empty($data['email']) && !empty($params['sendgrid_api_key'])){
					$result = $this->form_model->create_sendgrid_subscriber($data, $params);
				}
				
			}
			
			$return['message'] = 'ok';
			
			if (!empty($GLOBALS['config']['errors_visible']) && !empty($result)){
				$return['result'] = $result;
			}

			return $return;
        
        }
        
        return $params;
	
	}
	
}
