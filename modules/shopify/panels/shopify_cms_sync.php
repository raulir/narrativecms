<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_cms_sync extends \Controller {

	/**
	 * Settings UI field only. Sync runs solely on explicit do=sync_start (button ajax).
	 * Cron uses shopify/shopify_cron_sync — never empty-do here (avoids settings page load cost).
	 */
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

		if ($do !== 'sync_start'){
			return $params;
		}

		$this->load->model('shopify/shopify_product_model');

		$result = $this->shopify_product_model->run_sync_batch(50);
		$params['result'] = $result;
		$params['message'] = !empty($result['text']) ? $result['text'] : 'Shopify sync finished';

		return $params;

	}

	function panel_params($params){

		add_css('modules/shopify/css/shopify_cms_sync.scss');

		return $params;

	}

}
