<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Extends shop/product: catalogue freshness. Dims/items come from shop after sync.
 *
 * Chain (this site): shop → shopify → imagemaker (FK extend) → timmy
 * After catalogue merge, shop_image_model applies image_compose (provides).
 */
class shop_product extends \Controller {

	function panel_params($params){

		if (!is_array($params)){
			return $params;
		}

		$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($cms_page_panel_id <= 0){
			return $params;
		}

		$this->load->model('shopify/shopify_product_model');
		$product = $this->shopify_product_model->get_product_by_id($cms_page_panel_id);

		if (empty($product) || empty($product['cms_page_panel_id'])){
			if (empty($params['shopify_id'])){
				return $params;
			}
			$params['error'] = 1;
			if (empty($params['heading'])){
				$params['heading'] = 'Product unavailable';
			}
			return $params;
		}

		// Catalogue fields (CMS row after optional refresh) — presentation must not re-copy these
		$catalogue_keys = [
				'heading', 'text', 'type', 'min_price', 'max_price', 'available',
				'shopify_status', 'shopify_id', 'image', 'images', 'last_update',
				'shopify_checked_at', 'update_time', 'product_type_id',
		];
		foreach ($catalogue_keys as $key){
			if (array_key_exists($key, $product)){
				$params[$key] = $product[$key];
			}
		}

		// Other product panel fields refresh may have updated (e.g. original_artwork)
		foreach (['original_artwork', 'original_artwork_src_hash', 'print_file', 'print_file_src_hash',
				'thumbnail_image', 'subcategory_id', 'imagemaker_style_id'] as $key){
			if (array_key_exists($key, $product) && ($product[$key] !== '' && $product[$key] !== null)){
				$params[$key] = $product[$key];
			}
		}

		$this->load->model('shop/shop_image_model');
		$params = $this->shop_image_model->apply_to_product_params($params);

		return $params;

	}

	/**
	 * Admin save: drop productthumb HTML for this product.
	 */
	function on_update($params){

		$id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($id > 0){
			$this->load->model('shopify/shopify_product_model');
			$this->shopify_product_model->invalidate_product_display_cache($id);
		}

		return $params;

	}

}
