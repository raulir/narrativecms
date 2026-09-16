<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * provides.shop_fulfilment — one queued email per recipient, only that recipient's lines.
 */
class fulfilment_email extends \Controller {

	function panel_action($params){

		if (!is_array($params)){
			$params = [];
		}

		$do = $params['do'] ?? '';
		if ($do !== 'fulfil'){
			return $params;
		}

		$order = $params['order'] ?? [];
		$lines = $params['lines'] ?? [];
		if (!is_array($lines) || !$lines){
			$params['ok'] = 1;
			$params['sent_line_ids'] = [];
			return $params;
		}

		$by_email = [];
		foreach ($lines as $line){
			$emails = $line['emails'] ?? [];
			if (!is_array($emails)){
				continue;
			}
			foreach ($emails as $email){
				$email = strtolower(trim((string)$email));
				if ($email === ''){
					continue;
				}
				if (!isset($by_email[$email])){
					$by_email[$email] = [];
				}
				$by_email[$email][] = $line;
			}
		}

		$this->load->model('cms/cms_email_model');
		$this->load->model('cms/cms_page_panel_model');

		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/fulfilment_email');
		if (!is_array($settings)){
			$settings = [];
		}

		$ok_emails = [];
		$failed = 0;

		foreach ($by_email as $email => $email_lines){

			$subject = $this->_fill($settings['subject'] ?? 'Fulfilment request — order {{order_id}}', $order);
			$intro = $this->_fill($settings['intro'] ?? '', $order);
			$body = $this->_body($order, $email_lines, $intro);

			$ok = $this->cms_email_model->send_mail($email, $subject, $body, [
					'auto_submitted' => 1,
			]);
			if (!$ok){
				$failed++;
				error_log_user('CMS error [shop/fulfilment_email]: enqueue failed to '.$email.
						' order '.($order['cms_page_panel_id'] ?? ''));
				continue;
			}
			$ok_emails[$email] = 1;

		}

		$sent_ids = [];
		foreach ($lines as $line){
			$lid = (int)($line['cms_page_panel_id'] ?? 0);
			if ($lid <= 0){
				continue;
			}
			$need = $line['emails'] ?? [];
			if (!is_array($need) || !$need){
				continue;
			}
			$all = true;
			foreach ($need as $email){
				$email = strtolower(trim((string)$email));
				if ($email === '' || empty($ok_emails[$email])){
					$all = false;
					break;
				}
			}
			if ($all){
				$sent_ids[] = $lid;
			}
		}

		$params['ok'] = $failed ? 0 : 1;
		$params['sent_line_ids'] = $sent_ids;
		if ($failed){
			$params['error'] = 'Some fulfilment emails failed to queue';
		}

		return $params;

	}

	function _shopify_order_label($order){

		$name = trim((string)($order['shopify_order_name'] ?? ''));
		if ($name !== ''){
			return $name;
		}
		return trim((string)($order['shopify_order_id'] ?? ''));

	}

	function _fill($template, $order){

		$shopify_label = $this->_shopify_order_label($order);
		$map = [
				'{{order_id}}' => (string)($order['heading'] ?? $order['cms_page_panel_id'] ?? ''),
				'{{shopify_order_id}}' => $shopify_label,
				'{{shopify_order_name}}' => $shopify_label,
				'{{buyer_name}}' => (string)($order['buyer_name'] ?? ''),
		];

		return str_replace(array_keys($map), array_values($map), (string)$template);

	}

	function _body($order, $lines, $intro){

		$parts = [];
		$intro = trim((string)$intro);
		if ($intro !== ''){
			$parts[] = $intro;
			$parts[] = '';
		}

		$parts[] = 'Order ID: '.($order['heading'] ?? $order['cms_page_panel_id'] ?? '');
		$shopify_label = $this->_shopify_order_label($order);
		if ($shopify_label !== ''){
			$parts[] = 'Shopify order: '.$shopify_label;
		}

		$buyer = trim((string)($order['buyer_name'] ?? ''));
		$email = trim((string)($order['buyer_email'] ?? ''));
		if ($buyer !== '' || $email !== ''){
			$parts[] = 'Buyer: '.trim($buyer.($email !== '' ? ' <'.$email.'>' : ''));
		}

		$address = $this->_address_block($order);
		if ($address !== ''){
			$parts[] = '';
			$parts[] = 'Delivery address:';
			$parts[] = $address;
		} else {
			$parts[] = '';
			$parts[] = 'Delivery address: not available';
		}

		$parts[] = '';
		$parts[] = 'Lines:';
		foreach ($lines as $line){
			$parts[] = '';
			$qty = (int)($line['qty'] ?? 1);
			$heading = trim((string)($line['heading'] ?? 'Item'));
			$cat = trim((string)($line['category_heading'] ?? ''));
			$parts[] = $qty.' × '.$heading.($cat !== '' ? ' ('.$cat.')' : '');
			$desc = trim((string)($line['description'] ?? ''));
			if ($desc !== ''){
				$parts[] = $desc;
			}
			if (!empty($line['print_file_url'])){
				$name = trim((string)($line['print_file_name'] ?? 'print file'));
				$parts[] = 'Print file ('.$name.'): '.$line['print_file_url'];
			}
		}

		return implode("\n", $parts);

	}

	function _address_block($order){

		$name = trim((string)($order['shipping_name'] ?? ''));
		$lines = [];
		if ($name !== ''){
			$lines[] = $name;
		}
		foreach (['shipping_address1', 'shipping_address2', 'shipping_city', 'shipping_county',
				'shipping_postcode', 'shipping_country'] as $key){
			$v = trim((string)($order[$key] ?? ''));
			if ($v !== ''){
				$lines[] = $v;
			}
		}
		$phone = trim((string)($order['shipping_phone'] ?? ''));
		if ($phone !== ''){
			$lines[] = 'Tel: '.$phone;
		}

		return implode("\n", $lines);

	}

}
