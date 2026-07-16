<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_cms_purge extends \Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $this->input->post('do');
		if (empty($do) && !empty($params['do'])){
			$do = $params['do'];
		}

		$this->load->model('shopify/shopify_product_model');

		if ($do == 'purge_status'){

			$params['result'] = $this->shopify_product_model->purge_status_read();

		} else if ($do == 'purge_start'){

			set_time_limit(0);
			if (function_exists('ignore_user_abort')){
				ignore_user_abort(true);
			}

			// Release session lock so status polls can run while purge works
			if (session_status() === PHP_SESSION_ACTIVE){
				session_write_close();
			}

			$params['result'] = $this->shopify_product_model->purge_missing_products(50);

		}

		return $params;

	}

	function panel_params($params){

		add_css('modules/shopify/css/shopify_cms_purge.scss');

		return $params;

	}

}
