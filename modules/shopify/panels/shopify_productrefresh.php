<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_productrefresh extends \Controller {

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
			$params['cms_page_panel_id'] = $params['product_id'] ?? 0;
		}

		$params['show_refresh'] = 0;
		$id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($id > 0){
			$this->load->model('cms/cms_page_panel_model');
			$product = $this->cms_page_panel_model->get_cms_page_panel($id);
			if (!empty($product['shopify_id'])){
				$params['show_refresh'] = 1;
				$params['product_id'] = $id;
			}
		}

		return $params;

	}

}
