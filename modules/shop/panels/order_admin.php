<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * One order for the admin (admin/orders/{id}/, order_id): lines, summary, history; customer, delivery, status.
 */
class order_admin extends \Controller {

	function panel_action($params){

		if (!$this->_allowed()){
			return $params;
		}

		$order_id = (int)($params['order_id'] ?? 0);
		$do = (string)($params['cms_do'] ?? '');
		if ($order_id < 1 || $do === ''){
			return $params;
		}

		$this->load->model('shop/shop_model');

		// Payment first (Finished marks the lines fulfilled only on a paid order), then tracking, then the order
		// status; the dispatch email only when the status really became Fulfilled and Email the buyer is ticked.
		if ($do === 'save_status'){
			$this->load->model('cms/cms_page_panel_model');
			$before = $this->shop_model->order_physical_status($this->cms_page_panel_model->get_cms_page_panel($order_id));
			$this->shop_model->set_order_payment_status($order_id, (string)($params['payment_status'] ?? ''));
			if (isset($params['tracking_number'])){
				$this->shop_model->set_order_tracking($order_id, $params['tracking_number']);
			}
			$status = (string)($params['status'] ?? '');
			if (array_key_exists($status, $this->_status_options())){
				$result = $this->shop_model->set_order_status($order_id, $status);
				if ($result['status'] === 'fulfilled' && $before !== 'fulfilled' && (string)($params['email_buyer'] ?? '') === '1'){
					$this->shop_model->send_dispatch_email($order_id);
				}
			}
		}

		if ($do === 'add_comment'){
			$this->shop_model->add_order_comment($order_id, $params['comment'] ?? '');
		}

		// Finished order, goods sent back: the line's quantity goes back to stock (the model checks it may, once)
		if ($do === 'return_to_stock'){
			$this->shop_model->return_line_to_stock($order_id, (int)($params['line_id'] ?? 0));
		}

		return $params;

	}

	function panel_params($params){

		$params['order'] = [];
		if (!$this->_allowed()){
			return $params;
		}

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		$order_id = (int)($params['order_id'] ?? 0);
		$order = $order_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($order_id) : [];
		if (($order['panel_name'] ?? '') !== 'shop/order'){
			return $params;
		}

		$payable = $this->shop_model->get_payable_order($order_id);
		$currency = $payable['currency_row'] ?: $this->shop_model->get_shop_currency();
		$raw_lines = [];
		foreach ($this->shop_model->get_order_lines($order_id) as $line){
			$raw_lines[(int)$line['cms_page_panel_id']] = $line;
		}
		$lines = [];
		$item_qty = 0;
		$delivery_name = '';
		foreach ($payable['lines'] as $line){
			if ($line['type'] !== 'item'){
				$delivery_name = $delivery_name !== '' ? $delivery_name : (string)$line['name'];
				continue;
			}
			$raw = $raw_lines[$line['line_id']] ?? [];
			$line['image'] = (string)($raw['image'] ?? '');
			// Return to stock: finished order, posted line, not returned yet; or when it was returned
			$line['returnable'] = $raw && $this->shop_model->line_returnable($order, $raw);
			$line['returned'] = (int)($raw['stock_returned_time'] ?? 0) > 0 ? date('d M Y', (int)$raw['stock_returned_time']) : '';
			$line['unit_price'] = $this->shop_model->format_admin_amount($line['unit_price'], $currency);
			$line['total'] = $this->shop_model->format_admin_amount($line['total'], $currency);
			$item_qty += (int)$line['quantity'];
			$lines[] = $line;
		}

		foreach (['payment_provider', 'payment_ref', 'buyer_name', 'buyer_email', 'shipping_name', 'shipping_address1', 'shipping_address2', 'shipping_city', 'shipping_postcode', 'shipping_county', 'shipping_country', 'shipping_phone', 'payment_note', 'paid_amount', 'tracking_number'] as $field){
			$order[$field] = (string)($order[$field] ?? '');
		}

		$status = $this->shop_model->order_physical_status($order);
		$payment = $this->shop_model->order_payment_status($order);

		$history = [];
		foreach ($this->shop_model->get_order_log($order_id) as $row){
			$history[] = [
					'time' => date('d M Y H:i', (int)$row['time']),
					'line' => $row['line'],
					'comment' => $row['type'] === 'comment',
					'user_name' => (string)$row['user_name'],
			];
		}

		$params['order'] = $order;
		$params['heading'] = '#'.$order_id.' ('.strtolower($this->shop_model->order_payment_label($payment)).') ('.strtolower($this->shop_model->order_status_label($status)).')';
		$params['back_url'] = 'admin/orders/?view='.$this->_view($status);
		$params['lines'] = $lines;
		$params['item_qty'] = $item_qty;
		$params['delivery_name'] = $delivery_name;
		$params['subtotal'] = $this->shop_model->format_admin_amount($payable['subtotal'], $currency);
		$params['delivery_total'] = $this->shop_model->format_admin_amount($payable['delivery_total'], $currency);
		$params['total'] = $payable['mixed_currency'] ? '' : $this->shop_model->format_admin_amount($payable['total'], $currency);
		// What the provider charged; stays shown after a refund or a hand-set payment status.
		$params['paid'] = $order['paid_amount'] !== '' ? $this->shop_model->format_admin_amount($order['paid_amount'], $currency) : '';
		$params['refunded'] = (float)($order['refunded_amount'] ?? 0) > 0 ? '−'.$this->shop_model->format_admin_amount($order['refunded_amount'], $currency) : '';
		// Cancelling a paid order does not refund: the JS shows a note when Cancelled is chosen
		$params['refund_reminder'] = in_array($payment, ['paid', 'partially_refunded'], true);
		$params['history'] = $history;
		$params['status'] = $status;
		$params['status_label'] = $this->shop_model->order_status_label($status);
		$params['finished'] = $status === 'finished';
		// A draft or abandoned cart keeps its own state as the first choice (it cannot be set back to it later).
		$params['status_options'] = $this->_status_options();
		if (in_array($status, ['', 'abandoned'], true)){
			$params['status_options'] = [$status => $params['status_label']] + $params['status_options'];
		}
		// Dispatch email sent before: the tickbox starts unticked, so the buyer does not get it twice by accident.
		$params['dispatch_sent'] = (int)($order['dispatch_email_time'] ?? 0) > 0 ? date('d M Y H:i', (int)$order['dispatch_email_time']) : '';
		$params['payment_status'] = $payment;
		$params['payment_label'] = $this->shop_model->order_payment_label($payment);
		$params['payment_editable'] = $this->shop_model->payment_editable($order);
		$params['payment_options'] = [];
		foreach (['unpaid', 'pending', 'partially_paid', 'paid', 'partially_refunded', 'refunded', 'voided'] as $key){
			$params['payment_options'][$key] = $this->shop_model->order_payment_label($key);
		}

		return $params;

	}

	function _allowed(){

		$this->load->model('cms/cms_access_model');

		return $this->cms_access_model->cms_user_has_access('shop_orders');

	}

	function _status_options(){

		return [
				'unfulfilled' => 'Unfulfilled',
				'in_progress' => 'In progress',
				'fulfilled' => 'Fulfilled',
				'finished' => 'Finished',
				'cancelled' => 'Cancelled',
		];

	}

	/**
	 * Orders page filter the order belongs to (the back arrow opens it).
	 */
	function _view($status){

		if ($status === ''){
			return 'carts';
		}

		return in_array($status, ['finished', 'cancelled', 'abandoned'], true) ? 'archive' : 'orders';

	}

}
