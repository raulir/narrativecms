<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Paid unfulfilled-order fulfilment: group lines by category provider, run_action shop_fulfilment.
 * Skips when Shopify is in progress or fulfilled. Works for Stripe / CMS-only paid orders too.
 */
class shop_fulfilment_model extends \Model {

	function request_fulfilment($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$order_id = (int)$order_id;
		if ($order_id <= 0){
			return ['ok' => 0, 'error' => 'Order missing'];
		}

		$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
		if (empty($order['cms_page_panel_id']) || ($order['panel_name'] ?? '') !== 'shop/order'){
			error_log_user('CMS error [shop/fulfilment]: not a shop/order id='.$order_id);
			return ['ok' => 0, 'error' => 'Not a shop/order'];
		}

		if (!$this->shop_model->order_cms_fulfilment_ok($order)){
			if (!$this->shop_model->order_is_paid($order)){
				return ['ok' => 1, 'skipped' => 1, 'reason' => 'not_paid'];
			}
			return ['ok' => 1, 'skipped' => 1, 'reason' => 'shopify_fulfilment'];
		}

		$pending = $this->_pending_lines($order);
		if (!$pending){
			if (($order['fulfilment_sent_time'] ?? '') === ''){
				$this->cms_page_panel_model->update_cms_page_panel($order_id, [
						'fulfilment_sent_time' => time(),
				]);
			}
			return ['ok' => 1, 'unchanged' => 1];
		}

		$by_provider = [];
		foreach ($pending as $row){
			$panel = $row['provider'];
			if (!isset($by_provider[$panel])){
				$by_provider[$panel] = [];
			}
			$by_provider[$panel][] = $row;
		}

		$sent_ids = [];
		$failed = 0;

		foreach ($by_provider as $panel => $rows){

			$result = $this->_call_provider($panel, [
					'do' => 'fulfil',
					'order' => $order,
					'lines' => $rows,
			]);

			if (empty($result['ok'])){
				$failed++;
				error_log_user('CMS error [shop/fulfilment]: provider '.$panel.' failed order '.$order_id.
						' ('.($result['error'] ?? 'unknown').')');
				continue;
			}

			foreach (($result['sent_line_ids'] ?? []) as $lid){
				$lid = (int)$lid;
				if ($lid > 0){
					$sent_ids[$lid] = 1;
				}
			}

		}

		$now = time();
		foreach (array_keys($sent_ids) as $lid){
			$this->cms_page_panel_model->update_cms_page_panel($lid, [
					'fulfilment_sent_time' => $now,
					'status' => 'fulfilled',
			]);
		}

		$still = $this->_pending_lines($this->cms_page_panel_model->get_cms_page_panel($order_id));
		if (!$still && ($order['fulfilment_sent_time'] ?? '') === ''){
			$this->cms_page_panel_model->update_cms_page_panel($order_id, [
					'fulfilment_sent_time' => $now,
			]);
		}

		$this->shop_model->rollup_order_status($order_id);

		return [
				'ok' => $failed ? 0 : 1,
				'sent' => count($sent_ids),
				'failed' => $failed,
		];

	}

	/**
	 * Cron/safety: paid orders that still have unsent lines.
	 */
	function fulfil_pending_orders($max_seconds = 30){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$started = time();
		$max_seconds = max(5, (int)$max_seconds);
		$orders = $this->cms_page_panel_model->get_list('shop/order');
		$ran = 0;
		$sent = 0;

		foreach ($orders as $order){

			if ((time() - $started) >= $max_seconds){
				break;
			}
			if (!$this->shop_model->order_is_paid($order)){
				continue;
			}
			$physical = $this->shop_model->order_physical_status($order);
			if (in_array($physical, ['cancelled', 'abandoned', 'finished', ''], true)){
				continue;
			}
			if (!$this->_pending_lines($order)){
				continue;
			}

			if (in_array('shopify', $GLOBALS['config']['modules'] ?? [], true)
					&& trim((string)($order['shopify_order_id'] ?? '')) !== ''){
				$this->load->model('shopify/shopify_order_model');
				$refreshed = $this->shopify_order_model->refresh_shopify_order_status($order);
				if ($refreshed === null){
					continue;
				}
				$order = $refreshed;
				if (!$this->shop_model->order_cms_fulfilment_ok($order)){
					continue;
				}
			}

			$ran++;
			$result = $this->request_fulfilment($order['cms_page_panel_id']);
			$sent += (int)($result['sent'] ?? 0);

		}

		return [
				'text' => 'Fulfilment pending '.$ran.', lines queued '.$sent,
				'ran' => $ran,
				'sent' => $sent,
		];

	}

	function _is_delivery_line($line){

		$ref_id = (int)($line['ref_id'] ?? 0);
		if ($ref_id <= 0){
			return false;
		}
		$this->load->model('cms/cms_page_panel_model');
		$ref = $this->cms_page_panel_model->get_cms_page_panel($ref_id);
		return ($ref['panel_name'] ?? '') === 'shop/delivery';

	}

	function _pending_lines($order){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$order_id = (int)($order['cms_page_panel_id'] ?? 0);
		if ($order_id <= 0){
			return [];
		}

		$shopify_order = trim((string)($order['shopify_order_id'] ?? '')) !== '';
		$lines = $this->shop_model->get_order_lines($order_id);
		$out = [];

		foreach ($lines as $line){

			if (!empty($line['fulfilment_sent_time'])){
				continue;
			}
			$lst = $this->shop_model->order_line_status($line);
			if (in_array($lst, ['in_progress', 'fulfilled', 'cancelled'], true)){
				continue;
			}
			if ($this->_is_delivery_line($line)){
				continue;
			}
			if ($shopify_order && trim((string)($line['shopify_line_id'] ?? '')) === ''){
				continue;
			}

			$prepared = $this->_prepare_line($line);
			if ($prepared === null){
				continue;
			}
			$out[] = $prepared;

		}

		return $out;

	}

	function _prepare_line($line){

		$this->load->model('cms/cms_page_panel_model');

		$product = [];
		$product_id = (int)($line['product_id'] ?? 0);
		if ($product_id > 0){
			$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			if (($product['panel_name'] ?? '') !== 'shop/product'){
				$product = [];
			}
		}

		$category = $this->_category_for_product($product);
		$provider = trim((string)($category['fulfilment'] ?? ''));
		if ($provider === ''){
			return null;
		}

		$emails = $this->_emails_from_category($category);
		if (!$emails){
			error_log_user('CMS error [shop/fulfilment]: no emails for line '.
					($line['cms_page_panel_id'] ?? '').' category '.
					($category['cms_page_panel_id'] ?? ''));
			return null;
		}

		$print = $this->_print_file_url($product);

		$qty = (int)($line['qty'] ?? $line['quantity'] ?? 1);
		if ($qty < 1){
			$qty = 1;
		}

		$heading = trim((string)($line['item'] ?? ''));
		if ($heading === '' && !empty($product['heading'])){
			$heading = (string)$product['heading'];
		}

		$this->load->model('shop/shop_model');
		$item_id = $this->shop_model->order_line_item_id($line);
		$dim_text = '';
		if ($item_id > 0){
			$sku = $this->cms_page_panel_model->get_cms_page_panel($item_id);
			if (($sku['panel_name'] ?? '') === 'shop/product_item'){
				$this->load->model('shop/shop_dim_model');
				$dim_text = $this->shop_dim_model->dims_description($sku);
			}
		}
		$desc = trim((string)($line['description'] ?? ''));
		if ($dim_text !== ''){
			$desc = $desc !== '' ? $dim_text."\n".$desc : $dim_text;
		}

		return [
				'cms_page_panel_id' => (int)$line['cms_page_panel_id'],
				'qty' => $qty,
				'heading' => $heading,
				'description' => $desc,
				'emails' => $emails,
				'provider' => $provider,
				'print_file_url' => $print['url'],
				'print_file_name' => $print['name'],
				'category_heading' => (string)($category['heading'] ?? ''),
		];

	}

	function _category_for_product($product){

		$this->load->model('cms/cms_page_panel_model');

		$sub_id = (int)($product['subcategory_id'] ?? 0);
		if ($sub_id <= 0){
			return [];
		}
		$sub = $this->cms_page_panel_model->get_cms_page_panel($sub_id);
		$cat_id = (int)($sub['category_id'] ?? 0);
		if ($cat_id <= 0){
			return [];
		}
		$cat = $this->cms_page_panel_model->get_cms_page_panel($cat_id);
		if (($cat['panel_name'] ?? '') !== 'shop/category'){
			return [];
		}

		return $cat;

	}

	function _emails_from_category($category){

		$out = [];
		$rows = $category['fulfilment_emails'] ?? [];
		if (!is_array($rows)){
			return [];
		}
		foreach ($rows as $row){
			$email = strtolower(trim((string)($row['email'] ?? '')));
			if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
				continue;
			}
			$out[$email] = $email;
		}

		return array_values($out);

	}

	function _print_file_url($product){

		$rel = trim((string)($product['print_file'] ?? ''));
		if ($rel === ''){
			return ['url' => '', 'name' => ''];
		}

		$abs = rtrim((string)($GLOBALS['config']['upload_path'] ?? ''), '/\\').DIRECTORY_SEPARATOR.
				str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim($rel, '/\\'));
		if (!is_file($abs)){
			return ['url' => '', 'name' => ''];
		}

		$path = 'files/get/'.str_replace('/', '__', $rel);
		$url = function_exists('_l') ? _l($path, false) : (($GLOBALS['config']['base_url'] ?? '/').$path);
		if (!preg_match('#^https?://#i', (string)$url)){
			$host = trim((string)($GLOBALS['config']['base_site'] ?? ''));
			if ($host === ''){
				$h = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
				$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
						|| ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
						|| (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
								&& strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
				if ($h !== ''){
					$host = ($https ? 'https://' : 'http://').$h;
				}
			}
			$host = rtrim($host, '/');
			$url = $host !== '' ? $host.'/'.ltrim((string)$url, '/') : (string)$url;
		}

		return [
				'url' => $url,
				'name' => basename($rel),
		];

	}

	function _call_provider($panel, $params){

		$panel = trim((string)$panel);
		if ($panel === ''){
			return ['ok' => 0, 'error' => 'No provider'];
		}

		$CI = function_exists('get_instance') ? get_instance() : null;
		if ($CI === null || !method_exists($CI, 'run_action')){
			error_log_user('CMS error [shop/fulfilment]: no controller for provider call');
			return ['ok' => 0, 'error' => 'No controller'];
		}

		$params['return_result'] = 1;
		$params['no_html'] = 1;
		$result = $CI->run_action($panel, $params);
		if (!is_array($result)){
			return ['ok' => 0, 'error' => 'Provider failed'];
		}

		return $result;

	}

}
