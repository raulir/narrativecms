<?php

namespace shopify;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Write Shopify options/variants into shop/product_dim, product_type, product_item.
 * Sync only — storefront reads shop_dim_model.
 */
class shopify_dim_model extends \Model {

	function dim_slug($label){

		$s = strtolower(trim((string)$label));
		$s = preg_replace('/[^a-z0-9]/', '', $s);
		if ($s === ''){
			return '';
		}
		return substr($s, 0, 10);

	}

	function is_title_option($name){

		return strtolower(trim((string)$name)) === 'title';

	}

	/**
	 * Upsert dims, type, items; set image ids to product_item_id.
	 * Mutates $cms_product (product_type_id, images). Returns whether product fields changed.
	 */
	function sync_product(&$cms_product, $shopify_product){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		$product_id = (int)($cms_product['cms_page_panel_id'] ?? 0);
		if ($product_id <= 0 || !is_array($shopify_product)){
			return false;
		}

		$options = is_array($shopify_product['options'] ?? null) ? $shopify_product['options'] : [];
		$variants = is_array($shopify_product['variants'] ?? null) ? $shopify_product['variants'] : [];
		$changed = false;

		$dim_panels = $this->_upsert_dims($options);
		if ($dim_panels){
			$type_id = $this->_upsert_type($dim_panels, (string)($shopify_product['product_type'] ?? $cms_product['type'] ?? ''));
			if ($type_id > 0 && (int)($cms_product['product_type_id'] ?? 0) !== $type_id){
				$cms_product['product_type_id'] = $type_id;
				$changed = true;
			}
		}

		$item_by_variant = $this->_upsert_items($product_id, $dim_panels, $options, $variants, $cms_product);

		$images = is_array($cms_product['images'] ?? null) ? $cms_product['images'] : [];
		$shopify_images = is_array($shopify_product['images'] ?? null) ? $shopify_product['images'] : [];
		if ($this->_apply_image_item_ids($images, $shopify_images, $item_by_variant)){
			$cms_product['images'] = $images;
			$changed = true;
		}

		return $changed;

	}

	function _upsert_dims($options){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		$panels = [];
		foreach ($options as $option){
			if (!is_array($option)){
				continue;
			}
			$name = trim((string)($option['name'] ?? ''));
			if ($name === '' || $this->is_title_option($name)){
				continue;
			}
			$slug = $this->dim_slug($name);
			if ($slug === ''){
				continue;
			}

			$dim = $this->shop_dim_model->get_dim_by_id_slug($slug);
			$values = is_array($dim['values'] ?? null) ? $dim['values'] : [];
			$have = [];
			foreach ($values as $row){
				$vid = strtolower((string)($row['id'] ?? ''));
				if ($vid !== ''){
					$have[$vid] = true;
				}
			}

			$added = false;
			foreach (($option['values'] ?? []) as $raw){
				$label = is_array($raw) ? (string)($raw['label'] ?? $raw['value'] ?? '') : (string)$raw;
				$vslug = $this->dim_slug($label);
				if ($vslug === '' || !empty($have[$vslug])){
					continue;
				}
				$values[] = [
						'label' => $label !== '' ? $label : $vslug,
						'id' => $vslug,
						'description' => '',
				];
				$have[$vslug] = true;
				$added = true;
			}

			if (empty($dim['cms_page_panel_id'])){
				$new_id = $this->cms_page_panel_model->create_cms_page_panel([
						'panel_name' => 'shop/product_dim',
						'show' => 1,
						'heading' => $name,
						'id' => $slug,
						'values' => $values,
				]);
				$dim = $this->cms_page_panel_model->get_cms_page_panel($new_id);
			} else if ($added){
				$this->cms_page_panel_model->update_cms_page_panel($dim['cms_page_panel_id'], [
						'values' => $values,
				]);
				$dim['values'] = $values;
			}

			if (!empty($dim['cms_page_panel_id'])){
				$panels[] = $dim;
			}
		}

		return $panels;

	}

	function _upsert_type($dim_panels, $heading){

		$this->load->model('cms/cms_page_panel_model');

		$want = [];
		foreach ($dim_panels as $dim){
			$did = (int)($dim['cms_page_panel_id'] ?? 0);
			if ($did > 0){
				$want[] = $did;
			}
		}
		sort($want, SORT_NUMERIC);

		foreach ($this->cms_page_panel_model->get_list('shop/product_type') as $type){
			$have = [];
			foreach (($type['dims'] ?? []) as $row){
				$did = (int)($row['product_dim_id'] ?? 0);
				if ($did > 0){
					$have[] = $did;
				}
			}
			sort($have, SORT_NUMERIC);
			if ($have === $want){
				return (int)$type['cms_page_panel_id'];
			}
		}

		$dims_rep = [];
		foreach ($want as $did){
			$dims_rep[] = ['product_dim_id' => $did];
		}
		$heading = trim((string)$heading);
		if ($heading === ''){
			$names = [];
			foreach ($dim_panels as $dim){
				$names[] = $dim['heading'] ?? $dim['id'] ?? '';
			}
			$heading = trim(implode(' + ', array_filter($names)));
			if ($heading === ''){
				$heading = $want ? 'Shopify' : 'No dims';
			}
		}

		return (int)$this->cms_page_panel_model->create_cms_page_panel([
				'panel_name' => 'shop/product_type',
				'show' => 1,
				'heading' => $heading,
				'stock_control' => 'none',
				'allow_sale_when_not_in_stock' => '0',
				'dims' => $dims_rep,
		]);

	}

	function _upsert_items($product_id, $dim_panels, $options, $variants, $cms_product){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_dim_model');

		$masters = $this->shop_dim_model->get_master_items($product_id, true);
		$by_variant = [];
		$by_id = [];
		foreach ($masters as $item){
			$iid = (int)($item['cms_page_panel_id'] ?? 0);
			if ($iid <= 0){
				continue;
			}
			$by_id[$iid] = $item;
			$vid = trim((string)($item['shopify_variant_id'] ?? ''));
			if ($vid !== ''){
				$by_variant[$vid] = $item;
			}
		}

		$seen = [];
		$option_slugs = [];
		foreach ($options as $option){
			if (!is_array($option) || $this->is_title_option($option['name'] ?? '')){
				continue;
			}
			$pos = (int)($option['position'] ?? (count($option_slugs) + 1));
			$option_slugs[$pos] = $this->dim_slug($option['name'] ?? '');
		}

		$dim_by_slug = [];
		foreach ($dim_panels as $dim){
			$slug = strtolower((string)($dim['id'] ?? ''));
			if ($slug !== ''){
				$dim_by_slug[$slug] = $dim;
			}
		}

		foreach ($variants as $variant){
			if (!is_array($variant)){
				continue;
			}
			$vid = (string)($variant['id'] ?? '');
			if ($vid === ''){
				continue;
			}

			$dims_rows = [];
			for ($i = 1; $i <= 3; $i++){
				$raw = trim((string)($variant['option'.$i] ?? ''));
				if ($raw === '' || strtolower($raw) === 'default title'){
					continue;
				}
				$oslug = $option_slugs[$i] ?? $this->dim_slug($options[$i - 1]['name'] ?? '');
				if ($oslug === '' || empty($dim_by_slug[$oslug])){
					continue;
				}
				$dim = $dim_by_slug[$oslug];
				$vslug = $this->dim_slug($raw);
				if ($vslug === ''){
					continue;
				}
				$dims_rows[] = [
						'product_dim_id' => (int)$dim['cms_page_panel_id'],
						'value' => $vslug,
				];
			}

			$payload = [
					'panel_name' => 'shop/product_item',
					'show' => 1,
					'product_id' => $product_id,
					'price' => $variant['price'] ?? ($cms_product['price'] ?? ''),
					'sku' => $variant['sku'] ?? '',
					'number' => 1,
					'dims' => $dims_rows,
					'shopify_variant_id' => $vid,
			];

			$existing = $by_variant[$vid] ?? [];
			if (empty($existing['cms_page_panel_id'])){
				$found = $this->shop_dim_model->find_master_item($product_id, $this->shop_dim_model->item_dims_map(['dims' => $dims_rows]));
				if (!empty($found['cms_page_panel_id']) && empty($found['shopify_variant_id'])){
					$existing = $found;
				}
			}

			if (!empty($existing['cms_page_panel_id'])){
				$iid = (int)$existing['cms_page_panel_id'];
				$patch = [];
				foreach (['price', 'sku', 'dims', 'shopify_variant_id', 'show'] as $key){
					if (($existing[$key] ?? null) != $payload[$key]){
						$patch[$key] = $payload[$key];
					}
				}
				if ($patch){
					$this->cms_page_panel_model->update_cms_page_panel($iid, $patch);
				}
				$seen[$iid] = true;
				$by_variant[$vid] = array_merge($existing, $payload, ['cms_page_panel_id' => $iid]);
			} else {
				$iid = (int)$this->cms_page_panel_model->create_cms_page_panel($payload);
				if ($iid > 0){
					$seen[$iid] = true;
					$payload['cms_page_panel_id'] = $iid;
					$by_variant[$vid] = $payload;
				}
			}
		}

		foreach ($by_id as $iid => $item){
			if (!empty($seen[$iid])){
				continue;
			}
			if (trim((string)($item['shopify_variant_id'] ?? '')) === ''){
				continue;
			}
			if ((string)($item['show'] ?? '1') !== '0'){
				$this->cms_page_panel_model->update_cms_page_panel($iid, ['show' => 0]);
			}
		}

		$map = [];
		foreach ($by_variant as $vid => $item){
			$iid = (int)($item['cms_page_panel_id'] ?? 0);
			if ($iid > 0){
				$map[(string)$vid] = $iid;
			}
		}
		return $map;

	}

	function _apply_image_item_ids(&$images, $shopify_images, $item_by_variant){

		if (!is_array($images) || !$item_by_variant){
			return false;
		}

		$variant_ids_by_shopify_image = [];
		foreach ($shopify_images as $simg){
			if (!is_array($simg)){
				continue;
			}
			$sid = (string)($simg['id'] ?? '');
			if ($sid === ''){
				continue;
			}
			$variant_ids_by_shopify_image[$sid] = is_array($simg['variant_ids'] ?? null) ? $simg['variant_ids'] : [];
		}

		$changed = false;
		foreach ($images as $key => $img){
			if (!is_array($img)){
				continue;
			}
			$sid = (string)($img['shopify_id'] ?? '');
			$vlist = $variant_ids_by_shopify_image[$sid] ?? [];
			$ids = [];
			foreach ($vlist as $vid){
				$iid = $item_by_variant[(string)$vid] ?? 0;
				if ($iid > 0){
					$ids[] = (string)$iid;
				}
			}
			$old = $img['ids'] ?? [];
			if (!is_array($old)){
				$old = [];
			}
			$old = array_map('strval', $old);
			if ($ids !== $old){
				if ($ids){
					$images[$key]['ids'] = $ids;
				} else {
					unset($images[$key]['ids']);
				}
				$changed = true;
			}
		}

		return $changed;

	}

}
