<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class dim_value_select extends CI_Controller{
	
	function __construct(){
	
		parent::__construct();
	
		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
	
	}
	
	function panel_action($params){
		
		$do = $this->input->post('do');
		
		if ($do == 'set_dim'){
		
			$item_id = $this->input->post('item_id');
			$dimension = $this->input->post('dimension');
			$value = $this->input->post('value');
		
			$this->load->model('cms/cms_page_panel_model');
		
			// save data
			$this->cms_page_panel_model->update_cms_page_panel($item_id, ['dimensions' => [0 => ['value' => $dimension.'='.$value]]]);
		
		}
		
		return $params;
		
	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$item = $this->cms_page_panel_model->get_cms_page_panel($params['item_id']);
		
		$params['current'] = '';
		foreach($item['dimensions'] as $dim){
			if (stristr($dim['value'], $params['dimension'].'=')){
				$params['current'] = str_replace($params['dimension'].'=', '', $dim['value']);
			}
		}
		
		$params['available'] = [];
		
		$dimension_a = $this->cms_page_panel_model->get_list('cg/product_dimension', ['id' => $params['dimension']]);
		$dimension = array_pop($dimension_a);
		foreach($dimension['values'] as $value){
			$params['available'][$value['id']] = $value['label'];
		}
		
		return $params;
	
	}
	
}
