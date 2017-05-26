<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class do_send extends MY_Controller{
	
	function panel_action($params){

		$do = $this->input->post('do');
        if ($do == 'send_form'){

        	$cms_page_panel_id = $this->input->post('id');
        	
        	// collect data
        	$data = $this->input->post();
        	
			$this->load->model('cms_page_panel_model');
        	
        	if (empty($params['block_id'])){
        		$this->load->model('cms_page_panel_model');
        		$params_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('block_id' => $cms_page_panel_id, ));
        		$params = array_merge($params, $params_a[0]);
        	}
        	
        	unset($data['do']);
        	if (isset($data['id'])){
	        	unset($data['id']);
        	}
            if (isset($data['panel_id'])){
	        	unset($data['panel_id']);
        	}
        	if (isset($data['no_html'])){
        		unset($data['no_html']);
        	}
        	if (isset($data['cache'])){
        		unset($data['cache']);
        	}
        	
        	$this->load->model('form_model');
        	
        	// get global contact form params
        	$settings_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => 'form_settings', ));
        	$params['settings'] = !empty($settings_a[0]) ? $settings_a[0] : array();

			if(count($params['emails'])){
				$this->form_model->send_contact_request($params['emails'], $data, $params['title']);
			}
			
			if(!empty($params['autoreply'])){
				$this->form_model->send_autoreply($data, $params['autoreply_text'], 
						$params['autoreply_email'], $params['autoreply_name'], $params['autoreply_subject']);
			}
			
			$this->form_model->create_form_data($cms_page_panel_id, !empty($data['email']) ? $data['email'] : '', $data);
			
			$return = [];

			// add to cm or mailchimp
        	if (!empty($params['add_mailchimp']) && !empty($data['email']) && !empty($params['mailchimp_api_key']) && !empty($params['mailchimp_list_id'])){
				$result = $this->form_model->create_mailchimp_subscriber($data, $params);
			}
			
			if (!empty($params['add_cm']) && !empty($data['email']) && !empty($params['cm_api_key']) && !empty($params['cm_api_url']) && !empty($params['cm_list_id'])){
				$result = $this->form_model->create_cm_subscriber($data, $params);
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
