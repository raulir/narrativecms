<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class shopify_cms_sync extends \Controller {

	// No admin login gate — cron (visits / crontab) has no cms_user session.
	// Settings UI still only embeds this field inside authenticated admin.

	function panel_action($params){

		$do = $this->input->post('do');
		if (empty($do) && !empty($params['do'])){
			$do = $params['do'];
		}

		// Cron: empty do  |  Settings button: sync_start
		if (!empty($do) && $do !== 'sync_start'){
			return $params;
		}

		$this->load->model('shopify/shopify_product_model');

		set_time_limit(0);
		if (function_exists('ignore_user_abort')){
			ignore_user_abort(true);
		}

		// Do not hold session during long sync (visit-triggered cron must not block other requests)
		if (session_status() === PHP_SESSION_ACTIVE){
			session_write_close();
		}

		$result = $this->shopify_product_model->sync_products(50);
		$params['result'] = $result;
		$params['message'] = !empty($result['text']) ? $result['text'] : 'Shopify sync finished';

		return $params;

	}

	function panel_params($params){

		add_css('modules/shopify/css/shopify_cms_sync.scss');

		return $params;

	}

}
