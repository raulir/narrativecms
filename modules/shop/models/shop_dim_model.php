<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Product dims, product types, master/clone stock items.
 */
class shop_dim_model extends \Model {

	function product_type_defaults(){

		return [
				'cms_page_panel_id' => 0,
				'heading' => '',
				'stock_control' => 'none',
				'allow_sale_when_not_in_stock' => '0',
				'delivery_set_id' => 0,
				'panel' => '',
				'dims' => [],
		];

	}

	function resolve_product_type($product){

		$this->load->model('cms/cms_page_panel_model');
		$product = is_array($product) ? $product : [];

		$type_id = (int)($product['product_type_id'] ?? 0);
		if ($type_id <= 0){
			$sub_id = (int)($product['subcategory_id'] ?? 0);
			if ($sub_id > 0){
				$sub = $this->cms_page_panel_model->get_cms_page_panel($sub_id);
				if (is_array($sub)){
					$type_id = (int)($sub['product_type_id'] ?? 0);
					if ($type_id <= 0){
						$cat_id = (int)($sub['category_id'] ?? 0);
						if ($cat_id > 0){
							$cat = $this->cms_page_panel_model->get_cms_page_panel($cat_id);
							if (is_array($cat)){
								$type_id = (int)($cat['product_type_id'] ?? 0);
							}
						}
					}
				}
			}
		}

		if ($type_id <= 0){
			return $this->product_type_defaults();
		}

		$type = $this->cms_page_panel_model->get_cms_page_panel($type_id);
		if (empty($type['cms_page_panel_id'])){
			return $this->product_type_defaults();
		}

		if (empty($type['stock_control'])){
			$type['stock_control'] = 'none';
		}
		if (!isset($type['allow_sale_when_not_in_stock'])){
			$type['allow_sale_when_not_in_stock'] = '0';
		}
		if (empty($type['dims']) || !is_array($type['dims'])){
			$type['dims'] = [];
		}

		return $type;

	}

	function get_dim_by_id_slug($slug){

		$this->load->model('cms/cms_page_panel_model');
		$slug = strtolower(trim((string)$slug));
		if ($slug === ''){
			return [];
		}
		$list = $this->cms_page_panel_model->get_list('shop/product_dim', ['id' => $slug]);
		$dim = reset($list);
		return is_array($dim) ? $dim : [];

	}

	function get_dim_label($dim_slug_or_panel_id){

		$this->load->model('cms/cms_page_panel_model');
		if (is_numeric($dim_slug_or_panel_id) && (int)$dim_slug_or_panel_id > 0){
			$dim = $this->cms_page_panel_model->get_cms_page_panel((int)$dim_slug_or_panel_id);
			if (!empty($dim['heading'])){
				return $dim['heading'];
			}
			if (!empty($dim['id'])){
				return $dim['id'];
			}
			return '[no value]';
		}
		$dim = $this->get_dim_by_id_slug($dim_slug_or_panel_id);
		if (!empty($dim['heading'])){
			return $dim['heading'];
		}
		return '[no value]';

	}

	function get_dim_value_data($dim_slug_or_panel_id, $value_id){

		$this->load->model('cms/cms_page_panel_model');
		if (is_numeric($dim_slug_or_panel_id) && (int)$dim_slug_or_panel_id > 0){
			$dim = $this->cms_page_panel_model->get_cms_page_panel((int)$dim_slug_or_panel_id);
		} else {
			$dim = $this->get_dim_by_id_slug($dim_slug_or_panel_id);
		}
		$return = ['label' => (string)$value_id, 'description' => ''];
		if (empty($dim['values']) || !is_array($dim['values'])){
			return $return;
		}
		foreach ($dim['values'] as $value){
			if ((string)($value['id'] ?? '') === (string)$value_id){
				return [
						'label' => $value['label'] ?? (string)$value_id,
						'description' => $value['description'] ?? '',
				];
			}
		}
		return $return;

	}

	function item_dims_map($item){

		$map = [];
		$rows = is_array($item) ? ($item['dims'] ?? []) : [];
		if (!is_array($rows)){
			$rows = [];
		}
		foreach ($rows as $row){
			if (!is_array($row)){
				continue;
			}
			$did = (int)($row['product_dim_id'] ?? 0);
			$val = trim((string)($row['value'] ?? ''));
			if ($did > 0 && $val !== ''){
				$map[$did] = $val;
			}
		}
		ksort($map);
		return $map;

	}

	function item_dims_hash($item){

		$pairs = [];
		foreach ($this->item_dims_map($item) as $did => $val){
			$pairs[] = $did.'='.$val;
		}
		return md5(implode('|', $pairs));

	}

	function get_master_items($product_id, $include_hidden = false){

		$this->load->model('cms/cms_page_panel_model');
		$filter = [
				'panel_name' => 'shop/product_item',
				'cms_page_id' => 0,
				'product_id' => (int)$product_id,
		];
		if (!$include_hidden){
			$filter['show'] = '1';
		}
		$items = $this->cms_page_panel_model->get_cms_page_panels_by($filter);
		$masters = [];
		foreach ($items as $item){
			$oid = $item['order_id'] ?? '';
			if ($oid !== '' && $oid !== null && (int)$oid !== 0){
				continue;
			}
			$masters[] = $item;
		}
		return $masters;

	}

	function type_dim_panels($type){

		$this->load->model('cms/cms_page_panel_model');
		$panels = [];
		foreach (($type['dims'] ?? []) as $row){
			$did = (int)($row['product_dim_id'] ?? 0);
			if ($did <= 0){
				continue;
			}
			$dim = $this->cms_page_panel_model->get_cms_page_panel($did);
			if (!empty($dim['cms_page_panel_id'])){
				$panels[] = $dim;
			}
		}
		return $panels;

	}

	function normalize_dims_map($map){

		$this->load->model('cms/cms_page_panel_model');
		$want = [];
		foreach ((array)$map as $k => $v){
			$did = (int)$k;
			if ($did > 0){
				$dim = $this->cms_page_panel_model->get_cms_page_panel($did);
			} else {
				$dim = $this->get_dim_by_id_slug($k);
				$did = (int)($dim['cms_page_panel_id'] ?? 0);
			}
			$val = trim((string)$v);
			if ($did <= 0 || $val === '' || empty($dim['cms_page_panel_id'])){
				continue;
			}
			$resolved = $val;
			foreach (($dim['values'] ?? []) as $row){
				if (!is_array($row)){
					continue;
				}
				$vid = (string)($row['id'] ?? '');
				$label = (string)($row['label'] ?? '');
				if (strcasecmp($vid, $val) === 0 || strcasecmp($label, $val) === 0){
					$resolved = $vid !== '' ? $vid : $val;
					break;
				}
			}
			$want[$did] = $resolved;
		}
		ksort($want);
		return $want;

	}

	function dims_rows_from_map($map){

		$rows = [];
		foreach ($this->normalize_dims_map($map) as $did => $val){
			$rows[] = ['product_dim_id' => $did, 'value' => $val];
		}
		return $rows;

	}

	function dims_description_from_map($map){

		$bits = [];
		foreach ($this->normalize_dims_map($map) as $did => $val){
			$bits[] = $this->get_dim_label($did).': '.$this->get_dim_value_data($did, $val)['label'];
		}
		return implode(' ', $bits);

	}

	function cartesian_items_for_type($type){

		$sets = [];
		foreach ($this->type_dim_panels($type) as $dim){
			$vals = [];
			foreach (($dim['values'] ?? []) as $v){
				$vid = (string)($v['id'] ?? '');
				if ($vid !== ''){
					$vals[] = $vid;
				}
			}
			if (!$vals){
				return [];
			}
			$sets[] = ['did' => (int)$dim['cms_page_panel_id'], 'values' => $vals];
		}
		if (!$sets){
			return [];
		}

		$combos = [[]];
		foreach ($sets as $set){
			$next = [];
			foreach ($combos as $combo){
				foreach ($set['values'] as $vid){
					$row = $combo;
					$row[] = ['product_dim_id' => $set['did'], 'value' => $vid];
					$next[] = $row;
				}
			}
			$combos = $next;
		}

		$items = [];
		$i = 0;
		foreach ($combos as $dims){
			$i++;
			$items[] = [
					'cms_page_panel_id' => 'local-'.$i,
					'number' => 0,
					'price' => '',
					'dims' => $dims,
			];
		}
		return $items;

	}

	function find_master_item($product_id, $dims_map){

		$want = $this->normalize_dims_map($dims_map);
		$parts = [];
		foreach ($want as $d => $v){
			$parts[] = $d.'='.$v;
		}
		$want_hash = md5(implode('|', $parts));

		foreach ($this->get_master_items($product_id) as $item){
			if ($this->item_dims_hash($item) === $want_hash){
				return $item;
			}
		}
		return [];

	}

	function find_master_by_shopify_variant($product_id, $variant_id){

		$variant_id = preg_replace('/^gid:\\/\\/shopify\\/ProductVariant\\//', '', trim((string)$variant_id));
		if ($variant_id === ''){
			return [];
		}
		foreach ($this->get_master_items((int)$product_id, true) as $item){
			$vid = preg_replace('/^gid:\\/\\/shopify\\/ProductVariant\\//', '', trim((string)($item['shopify_variant_id'] ?? '')));
			if ($vid !== '' && $vid === $variant_id){
				return $item;
			}
		}
		return [];

	}

	/**
	 * Cart add: pickers are truth. Posted dims must match a sold master; do not
	 * fall back to a stale product_item_id. No dims → item id or the lone master.
	 */
	function resolve_cart_item($product_id, $product_item_id, $dims_map = []){

		$this->load->model('cms/cms_page_panel_model');

		$product_id = (int)$product_id;
		$has_dims = false;
		foreach ((array)$dims_map as $v){
			if (is_array($v)){
				$v = $v['value'] ?? '';
			}
			if (trim((string)$v) !== ''){
				$has_dims = true;
				break;
			}
		}

		if ($has_dims && $product_id > 0){
			$found = $this->find_master_item($product_id, $dims_map);
			if (!empty($found['cms_page_panel_id'])){
				return $found;
			}
			return [];
		}

		$product_item_id = (int)$product_item_id;
		if ($product_item_id > 0){
			$item = $this->cms_page_panel_model->get_cms_page_panel($product_item_id);
			if (($item['panel_name'] ?? '') === 'shop/product_item'){
				$oid = $item['order_id'] ?? '';
				$master = ($oid === '' || $oid === null || (int)$oid === 0);
				$item_pid = (int)($item['product_id'] ?? 0);
				if ($master && ($product_id <= 0 || $item_pid === $product_id)){
					return $item;
				}
			}
		}

		if ($product_id > 0){
			$masters = $this->get_master_items($product_id);
			if (count($masters) === 1){
				return reset($masters);
			}
		}

		return [];

	}

	/**
	 * Qty on unfinished orders that still point at this master (draft + live paid).
	 * Finished / abandoned / cancelled / refunded do not hold. Can exceed listed number.
	 */
	function held_qty_for_item($item_id, $exclude_order_id = 0){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$item_id = (int)$item_id;
		if ($item_id <= 0){
			return 0;
		}

		$by_ref = $this->cms_page_panel_model->get_list('shop/order_line', ['ref_id' => $item_id]);
		$by_item = $this->cms_page_panel_model->get_list('shop/order_line', ['product_item_id' => $item_id]);
		$seen = [];
		$qty = 0;

		foreach (array_merge($by_ref, $by_item) as $line){
			$lid = (int)($line['cms_page_panel_id'] ?? 0);
			if ($lid <= 0 || isset($seen[$lid])){
				continue;
			}
			$seen[$lid] = 1;
			$oid = (int)($line['order_id'] ?? 0);
			if ($exclude_order_id && $oid === (int)$exclude_order_id){
				continue;
			}
			if ($oid <= 0){
				continue;
			}
			$order = $this->cms_page_panel_model->get_cms_page_panel($oid);
			if (empty($order['cms_page_panel_id'])){
				continue;
			}
			if (!$this->shop_model->order_holds_stock($order)){
				continue;
			}
			$qty += (int)($line['qty'] ?? $line['quantity'] ?? 1);
		}

		return $qty;

	}

	function master_available($item, $exclude_order_id = 0){

		$number = (int)($item['number'] ?? 0);
		return $number - $this->held_qty_for_item((int)($item['cms_page_panel_id'] ?? 0), $exclude_order_id);

	}

	function item_stock_control($item){

		if (!is_array($item) || empty($item['product_id'])){
			return 'none';
		}
		$this->load->model('cms/cms_page_panel_model');
		$product = $this->cms_page_panel_model->get_cms_page_panel($item['product_id']);
		$type = $this->resolve_product_type(is_array($product) ? $product : []);
		return (string)($type['stock_control'] ?? 'none');

	}

	function type_allows_negative($type){

		$v = $type['allow_sale_when_not_in_stock'] ?? '0';
		return $v === '1' || $v === 1 || $v === true || $v === 'yes';

	}

	function get_product_dim_variations($product_id, $params = []){

		$this->load->model('cms/cms_page_panel_model');
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		$type = $this->resolve_product_type($product);
		$filter = is_array($params['dims'] ?? null) ? $params['dims'] : [];
		$items = $this->get_master_items($product_id);
		if (!$items && ($type['stock_control'] ?? 'none') !== 'count'){
			$items = $this->cartesian_items_for_type($type);
		}

		$return = [];
		foreach ($items as $item){
			$map = $this->item_dims_map($item);
			if ($filter){
				$ok = true;
				foreach ($filter as $fk => $fv){
					$did = (int)$fk;
					if ($did <= 0){
						$dim = $this->get_dim_by_id_slug($fk);
						$did = (int)($dim['cms_page_panel_id'] ?? 0);
					}
					if ($did <= 0 || strtolower((string)($map[$did] ?? '')) !== strtolower((string)$fv)){
						$ok = false;
						break;
					}
				}
				if (!$ok){
					continue;
				}
			}

			$price = !empty($item['price']) ? $item['price'] : ($product['price'] ?? 0);
			foreach ($map as $did => $val){
				$dim = $this->cms_page_panel_model->get_cms_page_panel($did);
				$slug = !empty($dim['id']) ? $dim['id'] : (string)$did;
				if (empty($return[$slug])){
					$return[$slug] = [];
				}
				if (empty($return[$slug][$val])){
					$return[$slug][$val] = [
							'data' => $this->get_dim_value_data($did, $val),
							'count' => 0,
							'items' => [],
							'availability' => [],
					];
				}
				$return[$slug][$val]['count'] += (int)($item['number'] ?? 0);
				$return[$slug][$val]['items'][$item['cms_page_panel_id']] = $price;
				foreach ($map as $odid => $oval){
					if ((int)$odid === (int)$did){
						continue;
					}
					$odim = $this->cms_page_panel_model->get_cms_page_panel($odid);
					$oslug = !empty($odim['id']) ? $odim['id'] : (string)$odid;
					$return[$slug][$val]['availability'][$oslug][$oval] = true;
				}
			}
		}

		foreach ($return as &$dim_data){
			ksort($dim_data);
		}

		return $return;

	}

	function get_product_presentation($product){

		$this->load->model('cms/cms_page_panel_model');
		$product = is_array($product) ? $product : [];
		$product_id = (int)($product['cms_page_panel_id'] ?? 0);
		$type = $this->resolve_product_type($product);
		$out = [
				'dims' => [],
				'variants' => [],
		];

		$dim_panels = $this->type_dim_panels($type);
		$masters = $product_id > 0 ? $this->get_master_items($product_id) : [];

		if (!$dim_panels && !$masters){
			return $out;
		}

		if ($masters){
			$used = [];
			foreach ($masters as $item){
				foreach ($this->item_dims_map($item) as $did => $val){
					$used[$did][$val] = true;
				}
			}
			if (!$dim_panels){
				foreach (array_keys($used) as $did){
					$dim = $this->cms_page_panel_model->get_cms_page_panel((int)$did);
					if (!empty($dim['cms_page_panel_id'])){
						$dim_panels[] = $dim;
					}
				}
			}

			$pos = 1;
			foreach ($dim_panels as $dim){
				$slug = strtolower((string)($dim['id'] ?? ''));
				if ($slug === ''){
					$slug = 'dim'.$dim['cms_page_panel_id'];
				}
				$did = (int)$dim['cms_page_panel_id'];
				$values = [];
				foreach (($dim['values'] ?? []) as $v){
					$vid = (string)($v['id'] ?? '');
					if ($vid === '' || empty($used[$did][$vid])){
						continue;
					}
					$values[] = [
							'value' => $v['label'] ?? $vid,
							'id' => $vid,
					];
				}
				if (!$values){
					continue;
				}
				$row = [
						'name' => $dim['heading'] ?? $slug,
						'slug' => $slug,
						'values' => $values,
				];
				$out['dims'][$pos] = $row;
				$pos++;
			}

			foreach ($masters as $item){
				$map = $this->item_dims_map($item);
				$variant = [
						'product_item_id' => (int)$item['cms_page_panel_id'],
						'shopify_variant_id' => (string)($item['shopify_variant_id'] ?? ''),
						'price' => !empty($item['price']) ? $item['price'] : ($product['price'] ?? ''),
						'heading' => '',
				];
				$labels = [];
				foreach ($dim_panels as $dim){
					$slug = strtolower((string)($dim['id'] ?? 'dim'.$dim['cms_page_panel_id']));
					$val = $map[(int)$dim['cms_page_panel_id']] ?? '';
					$info = $this->get_dim_value_data($dim['cms_page_panel_id'], $val);
					$label = $info['label'] ?? $val;
					$variant[$slug] = strtolower((string)$label);
					if ($label !== ''){
						$labels[] = $label;
					}
				}
				$variant['heading'] = implode(' / ', $labels);
				$out['variants'][$item['cms_page_panel_id']] = $variant;
			}

			return $out;
		}

		$pos = 1;
		$value_sets = [];
		foreach ($dim_panels as $dim){
			$slug = strtolower((string)($dim['id'] ?? ''));
			if ($slug === ''){
				$slug = 'dim'.$dim['cms_page_panel_id'];
			}
			$values = [];
			foreach (($dim['values'] ?? []) as $v){
				$vid = (string)($v['id'] ?? '');
				if ($vid === ''){
					continue;
				}
				$values[] = [
						'value' => $v['label'] ?? $vid,
						'id' => $vid,
				];
			}
			$row = [
					'name' => $dim['heading'] ?? $slug,
					'slug' => $slug,
					'values' => $values,
			];
			$out['dims'][$pos] = $row;
			$value_sets[] = ['slug' => $slug, 'did' => (int)$dim['cms_page_panel_id'], 'values' => $values];
			$pos++;
		}

		$combos = [[]];
		foreach ($value_sets as $set){
			$next = [];
			foreach ($combos as $combo){
				foreach ($set['values'] as $v){
					$row = $combo;
					$row[$set['slug']] = strtolower((string)$v['value']);
					$row['_ids'][$set['did']] = $v['id'];
					$next[] = $row;
				}
			}
			$combos = $next;
		}
		$i = 0;
		foreach ($combos as $combo){
			$i++;
			$ids = $combo['_ids'] ?? [];
			unset($combo['_ids']);
			$variant = [
					'product_item_id' => 0,
					'shopify_variant_id' => '',
					'price' => $product['price'] ?? '',
					'heading' => implode(' / ', array_values($combo)),
					'dim_ids' => $ids,
			];
			foreach ($combo as $slug => $label){
				$variant[$slug] = $label;
			}
			$out['variants']['local-'.$i] = $variant;
		}

		return $out;

	}

	function cart_line_is_shopify($line){

		if (!empty($line['shopify_variant_id']) || !empty($line['merchandise_id'])){
			return true;
		}
		return ((string)($line['line_type'] ?? '')) === 'shopify_variant';

	}

	function cart_line_is_local($line){

		if ($this->cart_line_is_shopify($line)){
			return false;
		}
		if (((string)($line['line_type'] ?? '')) === 'local_item'){
			return true;
		}
		$this->load->model('shop/shop_model');
		$item_id = $this->shop_model->order_line_item_id($line);
		if ($item_id > 0){
			$this->load->model('cms/cms_page_panel_model');
			$ref = $this->cms_page_panel_model->get_cms_page_panel($item_id);
			if (($ref['panel_name'] ?? '') === 'shop/product_item'){
				return true;
			}
		}
		return false;

	}

	function cart_source_conflict($order, $adding_local){

		if (empty($order['cms_page_panel_id'])){
			return false;
		}
		$this->load->model('shop/shop_model');
		$lines = $this->shop_model->get_order_lines($order['cms_page_panel_id']);
		$has_shopify = false;
		$has_local = false;
		foreach ($lines as $line){
			if ($this->cart_line_is_shopify($line)){
				$has_shopify = true;
			}
			if ($this->cart_line_is_local($line)){
				$has_local = true;
			}
		}
		if ($adding_local && $has_shopify){
			return true;
		}
		if (!$adding_local && $has_local){
			return true;
		}
		return false;

	}

	function dims_description($item){

		return $this->dims_description_from_map($this->item_dims_map($item));

	}

}
