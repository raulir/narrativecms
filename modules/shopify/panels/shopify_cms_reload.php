<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_cms_reload extends \Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params = []){

		if (!is_array($params)){
			$params = [];
		}

		$do = '';
		if (is_object($this->input) && method_exists($this->input, 'post')){
			$do = $this->input->post('do');
		}
		if ($do === null || $do === false || $do === ''){
			$do = $params['do'] ?? ($_POST['do'] ?? '');
		}

		$this->load->model('shopify/shopify_product_model');

		if ($do == 'reload_clear'){

			$params['result'] = $this->shopify_product_model->mark_all_products_sync_needed();

		} else if ($do == 'reload_recover'){

			// Restore shopify_id on products wiped by accidental purge=true updates
			$params['result'] = $this->shopify_product_model->recover_missing_shopify_ids();

		}

		return $params;

	}

	function panel_params($params){

		add_css('modules/shopify/css/shopify_cms_reload.scss');

		return $params;

	}

}
