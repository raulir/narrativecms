<?php

namespace emailer;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Newsletter signup. Stores rows via form_model; does not extend form/basic.
 */
class subscribe extends \Controller {

	function panel_params($params){

		if (!is_array($params)){
			$params = [];
		}

		$code = trim((string)($this->input->get('confirmation_code') ?? ''));
		if ($code !== ''){
			$confirmed = $this->_confirm_code($code);
			if ($confirmed){
				$params['confirm_result'] = 1;
			}
		}

		return $params;

	}

	function panel_action($params){

		if (!is_array($params)){
			$params = [];
		}

		$do = (string)$this->input->post('do');
		if ($do !== 'send_form'){
			return $params;
		}

		if (!$this->_form_ready()){
			error_log_user('emailer/subscribe: form module is not enabled; cannot store signup');
			return ['error' => 'Form module is not enabled'];
		}

		$email = trim((string)$this->input->post('email'));
		if ($email === '' || strpos($email, '@') === false){
			return ['error' => trim((string)($params['error_message'] ?? 'Please enter a valid email.'))];
		}

		$this->load->model('form/form_model');
		$this->load->model('cms/cms_page_model');

		$cms_page_panel_id = (int)($this->input->post('cms_page_panel_id') ?: ($this->input->post('id') ?: ($params['cms_page_panel_id'] ?? 0)));
		if ($cms_page_panel_id < 1){
			error_log_user('emailer/subscribe: missing cms_page_panel_id on send_form');
			return ['error' => 'Missing form id'];
		}

		$data = [
			'email' => $email,
			'ip' => $this->_visitor_ip(),
		];
		$page_title = trim((string)$this->input->post('_page'));
		if ($page_title !== ''){
			$data['_page'] = $page_title;
		}

		$title_parts = [trim(str_replace('#page#', '', $GLOBALS['config']['site_title'] ?? ''), ' '.($GLOBALS['config']['site_title_delimiter'] ?? ''))];
		$page = [];
		if (!empty($params['cms_page_id'])){
			$page = $this->cms_page_model->get_page($params['cms_page_id']);
			if (!empty($page['title'])){
				$title_parts[] = $page['title'];
			}
		}
		$title = (!empty($GLOBALS['config']['environment']) ? '['.$GLOBALS['config']['environment'].'] ' : '').
				'New subscribe "'.($params['title'] ?? 'subscribe').'" submission on "'.implode(' - ', $title_parts).'"';

		if (!empty($params['emails']) && is_array($params['emails'])){
			$pm = [
				'reply_to' => [
					'email' => $email,
					'name' => $email,
				],
			];
			$this->form_model->send_info_contact($params['emails'], $data, $title, $pm);
		}

		if (!empty($params['autoreply'])){
			if (!empty($params['confirm'])){
				$data['confirmation_code'] = md5('CMS'.mt_rand(1000, 9999).$email);
				$autoreply_text = (string)($params['autoreply_text'] ?? '');
				if (!stristr($autoreply_text, '[codeurl]')){
					$params['autoreply_text'] = $autoreply_text."\r\n\r\n".'[codeurl]';
				}
				$slug = '';
				if (!empty($page['slug'])){
					$slug = $page['slug'];
				}
				$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
				$data['codeurl'] = ($https ? 'https' : 'http').'://'.($_SERVER['SERVER_NAME'] ?? '').
						($GLOBALS['config']['base_url'] ?? '/').ltrim((string)$slug, '/').'/?confirmation_code='.$data['confirmation_code'];
			}
			$this->form_model->send_autoreply($data, $params);
		}

		$this->form_model->create_form_data($cms_page_panel_id, $email, $data);

		$list_result = null;
		if (!empty($params['add_mailchimp']) && !empty($params['mailchimp_api_key']) && !empty($params['mailchimp_list_id'])){
			$list_result = $this->form_model->create_mailchimp_subscriber($data, $params);
		}
		if (!empty($params['add_cm']) && !empty($params['cm_api_key']) && !empty($params['cm_api_url']) && !empty($params['cm_list_id'])){
			$list_result = $this->form_model->create_cm_subscriber($data, $params);
		}
		if (!empty($params['add_sendgrid'])){
			if (empty($params['sendgrid_api_key']) && !empty($GLOBALS['config']['sendgrid_apikey'])){
				$params['sendgrid_api_key'] = $GLOBALS['config']['sendgrid_apikey'];
			}
			if (!empty($params['sendgrid_api_key'])){
				$list_result = $this->form_model->create_sendgrid_subscriber($data, $params);
			}
		}

		$return = ['message' => 'ok'];
		if (!empty($GLOBALS['config']['errors_visible']) && !empty($list_result)){
			$return['result'] = $list_result;
		}

		return $return;

	}

	protected function _confirm_code($code){

		if (!$this->_form_ready()){
			error_log_user('emailer/subscribe: form module is not enabled; cannot confirm code');
			return false;
		}

		$this->load->model('form/form_model');
		return (bool)$this->form_model->confirm_code($code);

	}

	protected function _form_ready(){

		return in_array('form', $GLOBALS['config']['modules'] ?? [], true);

	}

	protected function _visitor_ip(){

		if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
			$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
			$_SERVER['HTTP_CLIENT_IP'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
		}

		$client = $_SERVER['HTTP_CLIENT_IP'] ?? '';
		$forward = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
		$remote = $_SERVER['REMOTE_ADDR'] ?? '';

		if ($client !== '' && filter_var($client, FILTER_VALIDATE_IP)){
			return $client;
		}
		if ($forward !== '' && filter_var($forward, FILTER_VALIDATE_IP)){
			return $forward;
		}

		return $remote;

	}

}
