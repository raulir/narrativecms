<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * List-only shop/order. on_update posts listed stock when status becomes finished, and logs state changes;
 * on_delete removes the order's lines and history.
 */
class order extends \Controller {

	function on_update($params){

		$id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($id <= 0){
			return $params;
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		// Status changes are logged by set_order_status; a hand-set payment status here.
		if (isset($params['payment_status'])){
			$this->shop_model->log_order_state($this->cms_page_panel_model->get_cms_page_panel($id), ['payment_status' => (string)$params['payment_status']]);
		}

		$result = $this->shop_model->set_order_status($id, (string)($params['status'] ?? ''));
		$params['status'] = $result['status'];
		if (!empty($result['stock_posted_time'])){
			$params['stock_posted_time'] = $result['stock_posted_time'];
		}

		return $params;

	}

	/**
	 * Order row deleted (technical list, cart cleanup, merge of a guest cart): its lines and history go too.
	 */
	function on_delete($params){

		$this->load->model('shop/shop_model');
		$this->shop_model->delete_order_parts((int)($params['cms_page_panel_id'] ?? 0));

		return $params;

	}

}
