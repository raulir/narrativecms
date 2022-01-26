<?php

namespace form;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class form_grid_operations extends \CI_Controller {
	
	function panel_action($params){

		$do = $this->input->post('do');
        if ($do == 'delete_item'){

       		$this->load->model('form/form_model');
        	
        	$item_id = $this->input->post('item_id');
        	
        	$this->form_model->delete_form_data($item_id);
        	
        }

		return $params;
		
	}
	
}
