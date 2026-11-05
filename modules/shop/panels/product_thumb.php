<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Basic product card for grids — local catalogue only. Settings labels come in via _panel / ajax_panel.
 */
class product_thumb extends \Controller {

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');
		$this->load->model('shop/shop_dim_model');

		$product = $this->cms_page_panel_model->get_cms_page_panel((int)$params['cms_page_panel_id']);

		if (($product['panel_name'] ?? '') !== 'shop/product'){
			$params['product'] = [
					'error' => 1,
					'heading' => $params['unavailable_label'],
					'show' => 0,
			];
			return $params;
		}

		if (!$this->shop_model->product_is_shown($product)){
			$params['product'] = [
					'error' => 0,
					'heading' => $product['heading'],
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

		// Price and availability from the product page's default variant: available = offered and sellable.
		$type = $this->shop_dim_model->resolve_product_type($product);
		$held = $this->shop_dim_model->held_qty_map((int)$product['cms_page_panel_id'], $type);
		$item = $this->shop_dim_model->default_master_item(
				$this->shop_dim_model->storefront_masters($product, $type),
				$type,
				$held
		);
		$priced = $this->shop_model->get_item_price_in_currency($item, $product);

		$params['product'] = [
				'cms_page_panel_id' => (int)$product['cms_page_panel_id'],
				'heading' => $product['heading'],
				'image' => $image,
				'price' => $priced['formatted'],
				'available' => ($item && $this->shop_dim_model->item_can_sell($item, $type, $held)) ? 1 : 0,
				'show' => 1,
				'error' => 0,
		];

		return $params;

	}

}
