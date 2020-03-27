<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class order_model extends CI_Model {
	
	function delete_order_line($order_line_id){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->cms_page_panel_model->update_cms_page_panel($order_line_id, ['show' => 0], true);
		
		return true;
		
	}

}
