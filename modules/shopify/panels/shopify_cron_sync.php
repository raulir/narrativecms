<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Cron-only product sync. Invoked by cms_cron with panel_action and no do.
 * Settings UI uses shopify/shopify_cms_sync (do=sync_start only).
 */
class shopify_cron_sync extends \Controller {

	function panel_action($params = []){

		if (!is_array($params)){
			$params = [];
		}

		$this->load->model('shopify/shopify_product_model');

		$result = $this->shopify_product_model->run_sync_batch(50);
		$params['result'] = $result;
		$params['message'] = !empty($result['text']) ? $result['text'] : 'Shopify sync finished';

		return $params;

	}

}
