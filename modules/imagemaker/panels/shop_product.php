<?php

namespace imagemaker;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Extends shop/product: insert product composite into gallery images.
 *
 * Chain (this site): shop → shopify → imagemaker → timmy
 * Expects catalogue fields (images, original_artwork, style FKs) already on $params.
 */
class shop_product extends \Controller {

	function panel_params($params){

		if (!is_array($params)){
			return $params;
		}

		if (!empty($params['error'])){
			return $params;
		}

		if (!in_array('imagemaker', $GLOBALS['config']['modules'] ?? [], true)){
			return $params;
		}

		$this->load->model('imagemaker/imagemaker_model');
		if (!$this->imagemaker_model->is_available()){
			return $params;
		}

		$product_id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($product_id <= 0){
			return $params;
		}

		$artwork = trim((string)($params['original_artwork'] ?? ''));
		$style_id = $this->imagemaker_model->resolve_style_id($params);
		$path = $this->imagemaker_model->get_product_composite_image(
				$product_id,
				$artwork,
				$style_id
		);
		if ($path === ''){
			return $params;
		}

		$images = (!empty($params['images']) && is_array($params['images']))
				? $params['images']
				: [];

		$params['images'] = $this->imagemaker_model->apply_composite_to_images(
				$images,
				$path,
				['drop_main_image' => trim((string)($params['image'] ?? ''))]
		);

		return $params;

	}

}
