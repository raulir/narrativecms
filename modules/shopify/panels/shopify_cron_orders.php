<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Cron-only Shopify order id attach. Invoked by cms_cron with panel_action and no do.
 */
class shopify_cron_orders extends \Controller {

	function panel_action($params = []){

		if (!is_array($params)){
			$params = [];
		}

		$this->load->model('shopify/shopify_order_model');

		$result = $this->shopify_order_model->run_order_sync_batch(50);
		$backfill = $this->shopify_order_model->backfill_paid_shopify_lines(15);

		$params['result'] = $result;
		$params['backfill'] = $backfill;
		$parts = [
				!empty($result['text']) ? $result['text'] : 'Shopify order sync finished',
				$backfill['text'] ?? '',
		];
		$params['message'] = trim(implode("\n", array_filter($parts)));
		$unsynced = $result['unsynced'] ?? [];
		$linked = $result['linked'] ?? [];
		$idle = (int)($unsynced['attached'] ?? 0) === 0
				&& (int)($unsynced['abandoned'] ?? 0) === 0
				&& (int)($linked['updated'] ?? 0) === 0
				&& (int)($backfill['count'] ?? 0) === 0
				&& empty($result['error']);
		if ($idle){
			$params['message'] .= "\nnoop";
		}

		return $params;

	}

}
