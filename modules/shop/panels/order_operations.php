<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class order_operations extends MY_Controller{
	
	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'delete_order_line'){
			 
			$order_line_id = $this->input->post('id');
			$params['return_id'] = $this->input->post('return_id');

			$this->load->model('shop/order_model');
			$this->load->model('cms/cms_page_panel_model');

			$this->order_model->delete_order_line($order_line_id);
			
			// check if return_id is basketmini
			$return_panel = $this->cms_page_panel_model->get_cms_page_panel($params['return_id']);
			
			if ($return_panel['panel_name'] != 'shop/basketmini'){
				$params['return_id'] = 0;
			}

		}
		
		return $params;

	}
	
	function panel_params($params){

		return $params;
		
	}

}
