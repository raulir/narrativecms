<?php

namespace form;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class form_grid_operations extends \Controller {
	
	function panel_action($params){

		$do = $params['cms_do'] ?? false;
        if ($do == 'delete_item'){

       		$this->load->model('form/form_model');
        	
        	$item_id = $params['item_id'] ?? false;
        	
        	$this->form_model->delete_form_data($item_id);
        	
        }

		return $params;
		
	}
	
}
