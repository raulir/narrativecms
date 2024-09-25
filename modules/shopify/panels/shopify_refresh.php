<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_refresh extends CI_Controller {

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

			$this->shopify_product_model->refresh_products();
			 
		}
		
		return $params;

	}

}
