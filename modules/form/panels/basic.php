<?php

namespace form;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class basic extends \CI_Controller{
	
	function ds_subscribers($params){

		$this->load->model('form/form_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$settings = $this->cms_page_panel_model->get_cms_page_panel($params['id']);

		$fields_settings = [];
		foreach($settings['elements'] as $field_settings){
			$fields_settings[$field_settings['name']] = $field_settings; 
		}
		
		// schema
		if ($params['do'] == 'S'){

			$return = $params['fields'];

			$return[] = [
					'type' => 'text',
					'name' => 'time',
					'label' => '',
					'width' => '14',
					'align' => 'left',
					'order' => 10,
			];
			
			// get form fields
			$data = $this->form_model->get_form_data($params['id'], 1);
			foreach($data['fields'] as $field){
				
				if (!empty($fields_settings[$field])){
			
					$return_r = [
							'type' => 'text',
							'name' => $field,
							'label' => $fields_settings[$field]['label'],
							'width' => 20,
							'align' => 'left',
							'order' => 20,
					];
					
					if ($field == 'email'){
						$return_r['width'] = 30;
					}
					
					$return[] = $return_r;
				
				}
			
			}

		} else if ($params['do'] == 'L'){
			
			$return = [];
		
			$data = $this->form_model->get_form_data($params['id'], 2);

			foreach($data['table'] as $line){

				$return[] = $line;
					
			}

		}
	
		return $return;
		
	}
	
}
