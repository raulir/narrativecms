<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_productrefresh extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}
	
	function panel_action($params){

		$this->load->model('shopify/shopify_product_model');
		
		$do = $this->input->post('do');

		if ($do == 'refresh'){
			$this->shopify_product_model->refresh_product($params['product_id'], true);
		}
		
		return $params;

	}
	
	function panel_params($params){
		
		
		if (empty($params['cms_page_panel_id'])){
			$params['cms_page_panel_id'] = $params['product_id'];
		}
		
		return $params;
		
	}

}
