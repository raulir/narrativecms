<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Rows of the admin orders table: _panel() on first landing, ajax for filters, search and more rows.
 * Params: view (orders / archive / carts) or q (3+ characters), offset.
 */
class orders_admin_rows extends \Controller {

	function panel_params($params){

		$params['orders'] = [];
		$params['first_page'] = true;
		$this->load->model('cms/cms_access_model');
		if (!$this->cms_access_model->cms_user_has_access('shop_orders')){
			return $params;
		}

		$this->load->model('shop/shop_model');

		$view = in_array($params['view'] ?? '', ['orders', 'archive', 'carts'], true) ? $params['view'] : 'orders';
		$q = $this->shop_model->clean_visitor_text($params['q'] ?? '', 80);
		$offset = max(0, (int)($params['offset'] ?? 0));

		foreach ($this->shop_model->get_admin_orders(['view' => $view, 'q' => $q], $offset, 50) as $row){
			$params['orders'][] = $row + [
					'date' => $this->_date($row['created']),
					'payment_label' => $this->shop_model->order_payment_label($row['payment_status']),
					'status_label' => $row['status'] === '' ? 'Cart' : $this->shop_model->order_status_label($row['status']),
			];
		}
		$params['first_page'] = $offset === 0;

		return $params;

	}

	function _date($ts){

		$ts = (int)$ts;
		if ($ts < 1){
			return '';
		}
		$days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
		$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

		return $days[(int)date('w', $ts)].', '.date('j', $ts).' '.$months[(int)date('n', $ts) - 1].' '.date('H:i', $ts);

	}

}
