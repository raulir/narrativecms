<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Basic product card for grids — local catalogue only.
 */
class product_thumb extends \Controller {

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		// Shared labels from product_thumb settings
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('shop/product_thumb');
		if (!is_array($settings)){
			$settings = [];
		}
		$params = array_merge($settings, $params);

		$product_id = (int)($params['cms_page_panel_id'] ?? $params['product_id'] ?? 0);
		$product = [];
		if ($product_id > 0){
			$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			if (!is_array($product)){
				$product = [];
			}
		}

		if (empty($product['cms_page_panel_id'])){
			$params['product'] = [
					'error' => 1,
					'heading' => $params['unavailable_label'] ?? 'Unavailable',
					'show' => 0,
			];
			return $params;
		}

		if (!$this->shop_model->product_is_shown($product)){
			$params['product'] = [
					'error' => 0,
					'heading' => $product['heading'] ?? '',
					'show' => 0,
					'cms_page_panel_id' => (int)$product['cms_page_panel_id'],
			];
			return $params;
		}

		// Image: main field, then first gallery image
		$image = trim((string)($product['image'] ?? ''));
		if ($image === '' && !empty($product['images']) && is_array($product['images'])){
			foreach ($product['images'] as $row){
				$candidate = trim((string)($row['image'] ?? ''));
				if ($candidate !== ''){
					$image = $candidate;
					break;
				}
			}
		}

		// Price in default currency
		$currency = $this->shop_model->get_default_currency();
		$currency_id = $currency ? (int)$currency['cms_page_panel_id'] : 0;
		$priced = $this->shop_model->get_product_price_in_currency($product, $currency_id);

		// Available: only treat explicit 0 as sold out; empty/missing = available
		$available = true;
		if (array_key_exists('available', $product) && $product['available'] !== '' && $product['available'] !== null){
			$available = ((float)str_replace(',', '.', (string)$product['available'])) > 0;
		}

		$params['product'] = [
				'cms_page_panel_id' => (int)$product['cms_page_panel_id'],
				'heading' => $product['heading'] ?? '',
				'image' => $image,
				'price' => $priced['formatted'] ?? '',
				'available' => $available ? 1 : 0,
				'show' => 1,
				'error' => 0,
		];

		if (empty($params['sold_out_label'])){
			$params['sold_out_label'] = 'sold out';
		}
		if (empty($params['unavailable_label'])){
			$params['unavailable_label'] = 'unavailable';
		}

		return $params;

	}

}
