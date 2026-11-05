<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Base product catalogue panel. Site modules extend via config.json //shop_product
 * (template, CSS, JS, panel_params, optional panel_heading — last extender wins for heading).
 */
class product extends \Controller {

	/**
	 * Variant grid on the product admin page. Writes go through shop_dim_model.
	 */
	function ds_variants($params){

		$this->load->model('shop/shop_dim_model');

		$do = $params['cms_do'] ?? '';
		$product_id = (int)($params['id'] ?? 0);

		if ($do === 'S'){
			return $this->_variant_fields($product_id);
		}
		if ($do === 'L'){
			return $this->_variant_rows($product_id);
		}
		if ($do === 'C'){
			return $this->shop_dim_model->create_master_item($product_id);
		}
		if ($do === 'D'){
			return $this->shop_dim_model->delete_master_item($product_id, (int)($params['row_id'] ?? 0));
		}
		if ($do === 'U'){
			$col = (string)($params['col'] ?? '');
			if (preg_match('/^dim_(\d+)$/', $col, $dim_match)){
				return $this->shop_dim_model->set_master_item_dim(
						$product_id,
						(int)($params['row_id'] ?? 0),
						(int)$dim_match[1],
						$params['value'] ?? ''
				);
			}
			return $this->shop_dim_model->update_master_item_cell(
					$product_id,
					(int)($params['row_id'] ?? 0),
					$col,
					$params['value'] ?? ''
			);
		}

		return [];

	}

	function _variant_fields($product_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		$dims = [];
		$product = [];
		if ($product_id > 0){
			$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
			if (!empty($product['cms_page_panel_id'])){
				$type = $this->shop_dim_model->resolve_product_type($product);
				$dims = $this->shop_dim_model->type_dim_panels($type);
			}
		}

		$widths = $this->_variant_widths(count($dims) + 5);
		$fields = [];
		$i = 0;
		$order = 10;

		foreach ($dims as $dim){
			$dim_id = (int)$dim['cms_page_panel_id'];
			$fields[] = [
					'type' => 'shop/dim_value_select',
					'name' => 'dim_'.$dim_id,
					'label' => (string)($dim['heading'] ?? ''),
					'dim' => $dim_id,
					'width' => (string)$widths[$i],
					'align' => 'left',
					'order' => (string)$order,
			];
			$i++;
			$order += 10;
		}

		$fields[] = [
				'type' => 'cms/cms_grid_editable',
				'name' => 'sku',
				'label' => 'SKU',
				'default' => '',
				'width' => (string)$widths[$i],
				'align' => 'left',
				'order' => (string)$order,
		];
		$i++;
		$order += 10;
		$fields[] = [
				'type' => 'cms/cms_grid_editable',
				'name' => 'number',
				'label' => 'In stock',
				'default' => '1',
				'width' => (string)$widths[$i],
				'align' => 'center',
				'order' => (string)$order,
		];
		$i++;
		$order += 10;
		$fields[] = [
				'type' => 'text',
				'name' => 'reserved',
				'label' => 'Reserved',
				'width' => (string)$widths[$i],
				'align' => 'center',
				'order' => (string)$order,
		];
		$i++;
		$order += 10;
		$price_default = trim((string)($product['price'] ?? ''));
		$fields[] = [
				'type' => 'cms/cms_grid_editable',
				'name' => 'price',
				'label' => 'Price',
				'default' => $price_default,
				'width' => (string)$widths[$i],
				'align' => 'center',
				'order' => (string)$order,
		];
		$i++;
		$order += 10;
		$fields[] = [
				'type' => 'shop/item_yes_no',
				'name' => 'availability',
				'label' => 'Available',
				'default' => '1',
				'width' => (string)$widths[$i],
				'align' => 'center',
				'order' => (string)$order,
		];

		return $fields;

	}

	function _variant_rows($product_id){

		$this->load->model('shop/shop_dim_model');

		// Reserved = qty on unfinished orders and held carts (same map as the storefront stock checks).
		$held = $this->shop_dim_model->held_qty_map($product_id);
		$rows = [];
		foreach ($this->shop_dim_model->get_master_items($product_id) as $item){
			$item_id = (int)($item['cms_page_panel_id'] ?? 0);
			if ($item_id < 1){
				continue;
			}
			$map = $this->shop_dim_model->item_dims_map($item);
			$availability = (string)($item['availability'] ?? '1');
			if ($availability !== '0'){
				$availability = '1';
			}
			$row = [
					'id' => $item_id,
					'sku' => (string)($item['sku'] ?? ''),
					'number' => (string)($item['number'] ?? ''),
					'reserved' => (string)($held[$item_id] ?? 0),
					'price' => (string)($item['price'] ?? ''),
					'availability' => $availability,
			];
			foreach ($map as $dim_id => $value){
				$row['dim_'.$dim_id] = (string)$value;
			}
			$rows[] = $row;
		}

		return $rows;

	}

	function _variant_widths($count){

		$count = (int)$count;
		if ($count < 1){
			return [];
		}

		$base = intdiv(100, $count);
		$widths = array_fill(0, $count, $base);
		$extra = 100 - ($base * $count);
		for ($i = 0; $i < $extra; $i++){
			$widths[$i] += 1;
		}

		return $widths;

	}

	function panel_params($params){

		if (!empty($params['error'])){
			return $params;
		}

		$this->load->model('shop/shop_model');
		$this->load->model('shop/shop_dim_model');

		// buy_label / added_message come with the panel's stored settings.
		$pres = $this->shop_dim_model->get_product_presentation($params);
		$params['dims'] = $pres['dims'] ?? [];
		$params['variants'] = $pres['variants'] ?? [];

		$currency = $this->shop_model->get_default_currency();
		$params['price_prefix'] = is_array($currency) ? (string)($currency['sign'] ?? '') : '';

		$images = [];
		$main = trim((string)($params['image'] ?? ''));
		if ($main !== ''){
			$images[] = [
					'image' => $main,
					'ids' => [],
			];
		}
		if (!empty($params['images']) && is_array($params['images'])){
			foreach ($params['images'] as $row){
				if (!is_array($row)){
					continue;
				}
				$img = trim((string)($row['image'] ?? ''));
				if ($img === ''){
					continue;
				}
				$ids = $row['ids'] ?? [];
				if (!is_array($ids)){
					$ids = ($ids === '' || $ids === null) ? [] : [$ids];
				}
				$flat = [];
				foreach ($ids as $id_row){
					if (is_array($id_row)){
						$id_row = $id_row['product_item_id'] ?? $id_row['id'] ?? '';
					}
					foreach (preg_split('/\s*,\s*/', trim((string)$id_row)) as $one){
						if ($one !== '' && $one !== '0'){
							$flat[] = $one;
						}
					}
				}
				$ids = $flat;
				$images[] = [
						'image' => $img,
						'ids' => $ids,
						'heading' => (string)($row['heading'] ?? ''),
				];
			}
		}

		// Default variant sets the first price and image. Picker active/available flags
		// and the Buy item come from the model's picker cascade.
		$params['default_variant'] = $params['variants'][$pres['default_product_item_id']] ?? null;
		$params['buy_item_id'] = (int)$pres['selected_product_item_id'];

		$buy_class = 'product_buy_off';
		if ($params['buy_item_id'] > 0){
			$buy_class = '';
		} else if (!empty($params['variants'])){
			$buy_class = 'product_buy_disabled';
		}
		$params['buy_class'] = $buy_class;

		if (!empty($params['variants']) && is_array($params['variants'])){
			foreach ($params['variants'] as $vid => $variant){
				$priced = $this->shop_model->get_item_price_in_currency($variant['product_item_id'] ?? 0, $params, $currency);
				$params['variants'][$vid]['price'] = $priced['price'];
				$params['variants'][$vid]['price_display'] = $priced['formatted'];
				if (!empty($params['default_variant']['product_item_id'])
						&& (int)$params['default_variant']['product_item_id'] === (int)($variant['product_item_id'] ?? 0)){
					$params['default_variant']['price'] = $priced['price'];
					$params['default_variant']['price_display'] = $priced['formatted'];
				}
			}
		}
		if (!empty($params['default_variant']['price_display'])){
			$params['price_display'] = $params['default_variant']['price_display'];
		} else {
			$priced = $this->shop_model->get_item_price_in_currency(0, $params, $currency);
			$params['price_display'] = $priced['formatted'];
		}

		$default_item_id = (string)(($params['default_variant'] ?? [])['product_item_id'] ?? '');
		$has_specific = false;
		foreach ($images as $i => $image){
			$ids = array_map('strval', $image['ids'] ?? []);
			$images[$i]['ids'] = $ids;
			$specific = ($default_item_id !== '' && $default_item_id !== '0' && in_array($default_item_id, $ids, true));
			$images[$i]['novariant'] = $ids ? 0 : 1;
			$images[$i]['variant_active'] = $specific ? 1 : 0;
			if ($specific){
				$has_specific = true;
			}
		}
		if (!$has_specific){
			foreach ($images as $i => $image){
				if (!empty($image['novariant'])){
					$images[$i]['variant_active'] = 1;
				}
			}
		}
		$params['images'] = $images;

		return $params;

	}

	function panel_heading($params){

		$this->load->model('cms/cms_page_panel_model');

		$addon = '';
		if (!empty($params['subcategory_id'])){
			$subcategory = $this->cms_page_panel_model->get_cms_page_panel($params['subcategory_id']);
			if (is_array($subcategory) && !empty($subcategory['cms_page_panel_id'])){
				$parts = [];
				if (!empty($subcategory['category_id'])){
					$category = $this->cms_page_panel_model->get_cms_page_panel($subcategory['category_id']);
					if (is_array($category) && !empty($category['heading'])){
						$parts[] = $category['heading'];
					}
				}
				if (!empty($subcategory['heading'])){
					$parts[] = $subcategory['heading'];
				}
				if ($parts){
					$addon = ' ('.implode(' - ', $parts).')';
				}
			}
		}

		$return = '<div class="cms_heading_colour" style="background-color: '.($params['colour'] ?? '').'; "></div> '.($params['heading'] ?? '').$addon;

		return $return;

	}

	function on_update($data){

		$this->load->model('shop/shop_dim_model');

		$id = (int)($data['cms_page_panel_id'] ?? 0);
		if ($id > 0){
			$product = $data;
			$product['cms_page_panel_id'] = $id;
			if (($product['panel_name'] ?? '') === ''){
				$product['panel_name'] = 'shop/product';
			}
			$this->shop_dim_model->ensure_unlimited_items($product);
		}

		return $data;

	}

}
