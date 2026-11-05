<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Product dims, product types, master items, picker state and held stock.
 */
class shop_dim_model extends \Model {

	var $_skipped_logged = [];

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

	/**
	 * List title: product name, or product name plus dim value labels.
	 */
	function item_list_title($item){

		$this->load->model('cms/cms_page_panel_model');

		$item = is_array($item) ? $item : [];
		$product_id = (int)($item['product_id'] ?? 0);
		$product = $product_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($product_id) : [];
		if (!is_array($product)){
			$product = [];
		}

		$name = trim((string)($product['heading'] ?? ''));
		if ($name === ''){
			$name = 'Product';
		}

		$type = $this->resolve_product_type($product);
		$map = $this->item_dims_map($item);
		$labels = [];
		$seen = [];

		foreach ($this->type_dim_panels($type) as $dim){
			$did = (int)($dim['cms_page_panel_id'] ?? 0);
			if ($did < 1){
				continue;
			}
			$seen[$did] = 1;
			$val = (string)($map[$did] ?? '');
			if ($val === ''){
				continue;
			}
			$label = trim((string)($this->get_dim_value_data($did, $val)['label'] ?? ''));
			if ($label !== ''){
				$labels[] = $label;
			}
		}

		foreach ($map as $did => $val){
			if (isset($seen[$did])){
				continue;
			}
			$label = trim((string)($this->get_dim_value_data($did, $val)['label'] ?? ''));
			if ($label !== ''){
				$labels[] = $label;
			}
		}

		if (!$labels){
			return $name;
		}

		return $name.' ('.implode(', ', $labels).')';

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
		return $this->cms_page_panel_model->get_cms_page_panels_by($filter);

	}

	/**
	 * Product item that belongs to this product, or [].
	 */
	function master_item($product_id, $item_id){

		$this->load->model('cms/cms_page_panel_model');

		$product_id = (int)$product_id;
		$item_id = (int)$item_id;
		if ($product_id < 1 || $item_id < 1){
			return [];
		}

		$item = $this->cms_page_panel_model->get_cms_page_panel($item_id);
		if (($item['panel_name'] ?? '') !== 'shop/product_item'){
			return [];
		}
		if ((int)($item['product_id'] ?? 0) !== $product_id){
			return [];
		}

		return $item;

	}

	/**
	 * True when the item has a value for every dim id given (the product type's dims).
	 * No dims on the type: every master is complete.
	 */
	function master_is_complete($item, $dim_ids){

		$map = $this->item_dims_map($item);
		foreach ($dim_ids as $did){
			if (!isset($map[(int)$did])){
				return false;
			}
		}
		return true;

	}

	/**
	 * Masters the storefront and cart may use: visible, a value for every type dim,
	 * first row per combination. Skipped rows are logged once per request.
	 */
	function storefront_masters($product, $type = null){

		$product = is_array($product) ? $product : [];
		$product_id = (int)($product['cms_page_panel_id'] ?? 0);
		if ($product_id < 1){
			return [];
		}
		if (!is_array($type)){
			$type = $this->resolve_product_type($product);
		}

		$dim_ids = [];
		foreach ($this->type_dim_panels($type) as $dim){
			$dim_ids[] = (int)$dim['cms_page_panel_id'];
		}

		$out = [];
		$seen = [];
		foreach ($this->get_master_items($product_id) as $item){
			$item_id = (int)($item['cms_page_panel_id'] ?? 0);
			if (!$this->master_is_complete($item, $dim_ids)){
				$this->_log_skipped_master($item_id, $product_id, 'has no value for a product type option');
				continue;
			}
			$hash = $this->item_dims_hash($item);
			if (isset($seen[$hash])){
				$this->_log_skipped_master($item_id, $product_id, 'repeats the options of item '.$seen[$hash]);
				continue;
			}
			$seen[$hash] = $item_id;
			$out[] = $item;
		}
		return $out;

	}

	function _log_skipped_master($item_id, $product_id, $why){

		if (!empty($this->_skipped_logged[$item_id])){
			return;
		}
		$this->_skipped_logged[$item_id] = 1;
		error_log_user('shop: product item '.$item_id.' (product '.$product_id.') '.$why.', not shown');

	}

	/**
	 * Default variant: first sellable, else first offered, else first.
	 * $held: held_qty_map of the product; built here when not given.
	 */
	function default_master_item($masters, $type, $held = null){

		$first = reset($masters);
		if (!is_array($first)){
			return [];
		}
		if (!is_array($held)){
			$held = $this->held_qty_map((int)($first['product_id'] ?? 0), $type);
		}

		$offered = [];
		foreach ($masters as $item){
			if (!$this->item_is_offered($item)){
				continue;
			}
			if ($this->item_can_sell($item, $type, $held)){
				return $item;
			}
			if (!$offered){
				$offered = $item;
			}
		}
		return $offered ? $offered : $first;

	}

	function create_master_item($product_id, $dims_map = []){

		$this->load->model('cms/cms_page_panel_model');

		$product_id = (int)$product_id;
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (($product['panel_name'] ?? '') !== 'shop/product'){
			return ['_reason' => 'Product not found'];
		}

		$price = trim((string)($product['price'] ?? ''));
		if ($price === ''){
			$price = '0';
		}

		$stats = $this->cms_page_panel_model->get_sort_stats('shop/product_item');
		$sort = !empty($stats['max_sort']) ? ((int)$stats['max_sort'] + 1) : 1;

		$dims_rows = $this->dims_rows_from_map(is_array($dims_map) ? $dims_map : []);
		$list_title = $this->item_list_title([
				'product_id' => $product_id,
				'dims' => $dims_rows,
		]);

		$id = (int)$this->cms_page_panel_model->create_cms_page_panel([
				'panel_name' => 'shop/product_item',
				'cms_page_id' => 0,
				'parent_id' => 0,
				'sort' => $sort,
				'show' => 1,
				'title' => $list_title,
				'heading' => $list_title,
				'product_id' => $product_id,
				'availability' => '1',
				'dims' => $dims_rows,
				'number' => '1',
				'price' => $price,
				'sku' => '',
		]);

		if ($id < 1){
			return ['_reason' => 'Could not add a variant'];
		}

		return ['web' => 1, 'id' => $id];

	}

	function update_master_item_cell($product_id, $item_id, $col, $value){

		$this->load->model('cms/cms_page_panel_model');

		if (!$this->master_item($product_id, $item_id)){
			return ['_reason' => 'This row is not a variant of this product'];
		}

		$value = trim((string)$value);
		$col = (string)$col;

		if ($col === 'number'){
			if (!preg_match('/^-?\d+$/', $value)){
				return ['_reason' => 'In stock must be a whole number'];
			}
			$this->cms_page_panel_model->update_cms_page_panel((int)$item_id, ['number' => $value]);
			return ['web' => 1];
		}

		if ($col === 'price'){
			if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)){
				return ['_reason' => 'Price must be a number'];
			}
			$this->cms_page_panel_model->update_cms_page_panel((int)$item_id, ['price' => $value]);
			return ['web' => 1];
		}

		if ($col === 'sku'){
			$this->cms_page_panel_model->update_cms_page_panel((int)$item_id, ['sku' => $value]);
			return ['web' => 1];
		}

		if ($col === 'availability'){
			if ($value !== '0' && $value !== '1'){
				return ['_reason' => 'Availability must be yes or no'];
			}
			$this->cms_page_panel_model->update_cms_page_panel((int)$item_id, ['availability' => $value]);
			return ['web' => 1];
		}

		return ['_reason' => 'This column cannot be edited'];

	}

	function set_master_item_dim($product_id, $item_id, $dim_id, $value){

		$this->load->model('cms/cms_page_panel_model');

		$item = $this->master_item($product_id, $item_id);
		if (!$item){
			return ['_reason' => 'This row is not a variant of this product'];
		}

		$dim_id = (int)$dim_id;
		$value = trim((string)$value);
		$product = $this->cms_page_panel_model->get_cms_page_panel((int)$product_id);
		$type = $this->resolve_product_type($product);
		$dim = [];
		foreach ($this->type_dim_panels($type) as $row){
			if ((int)($row['cms_page_panel_id'] ?? 0) === $dim_id){
				$dim = $row;
				break;
			}
		}
		if (!$dim){
			return ['_reason' => 'This option is not on the product type'];
		}

		if (!$this->dim_has_value($dim, $value)){
			return ['_reason' => 'Unknown option value'];
		}

		// No unique-combination check here: grid Save applies one cell at a time, so an
		// in-between state may match another row. storefront_masters() skips duplicates.
		$map = $this->item_dims_map($item);
		$map[$dim_id] = $value;

		$preview = $item;
		$preview['product_id'] = $product_id;
		$preview['dims'] = $this->dims_rows_from_map($map);
		$heading = $this->item_list_title($preview);

		$this->cms_page_panel_model->update_cms_page_panel((int)$item_id, [
				'dims' => $preview['dims'],
				'heading' => $heading,
				'title' => $heading,
		]);

		return ['web' => 1];

	}

	function delete_master_item($product_id, $item_id){

		$this->load->model('cms/cms_page_panel_model');

		if (!$this->master_item($product_id, $item_id)){
			return ['_reason' => 'This row is not a variant of this product'];
		}
		if ($this->held_qty_for_item((int)$item_id) > 0){
			return ['_reason' => 'This variant is on an open order'];
		}

		$this->cms_page_panel_model->delete_cms_page_panel((int)$item_id);

		return ['web' => 1];

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

	function dim_has_value($dim, $value_id){

		foreach (($dim['values'] ?? []) as $row){
			if (is_array($row) && (string)($row['id'] ?? '') === (string)$value_id){
				return true;
			}
		}
		return false;

	}

	/**
	 * Map of product_dim_id => value id. Ids only: keys must be dim panel ids and
	 * values must be exact value ids of that dim. Anything else is dropped.
	 */
	function normalize_dims_map($map){

		$this->load->model('cms/cms_page_panel_model');
		$want = [];
		foreach ((array)$map as $k => $v){
			$did = (int)$k;
			$val = trim((string)$v);
			if ($did < 1 || (string)$did !== (string)$k || $val === ''){
				continue;
			}
			$dim = $this->cms_page_panel_model->get_cms_page_panel($did);
			if (($dim['panel_name'] ?? '') !== 'shop/product_dim' || !$this->dim_has_value($dim, $val)){
				continue;
			}
			$want[$did] = $val;
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
	 * Cart add: pickers are truth. Posted dims must match a storefront master; do not
	 * fall back to a stale product_item_id. No dims → item id or the lone master.
	 * Only storefront_masters() can be added (complete, first per combination).
	 */
	function resolve_cart_item($product_id, $product_item_id, $dims_map = []){

		$this->load->model('cms/cms_page_panel_model');

		$product_id = (int)$product_id;
		$product_item_id = (int)$product_item_id;
		if ($product_id < 1 && $product_item_id > 0){
			$item = $this->cms_page_panel_model->get_cms_page_panel($product_item_id);
			if (($item['panel_name'] ?? '') === 'shop/product_item'){
				$product_id = (int)($item['product_id'] ?? 0);
			}
		}
		if ($product_id < 1){
			return [];
		}
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (empty($product['cms_page_panel_id'])){
			return [];
		}
		$masters = $this->storefront_masters($product);

		$has_dims = false;
		foreach ((array)$dims_map as $v){
			if (trim((string)$v) !== ''){
				$has_dims = true;
				break;
			}
		}

		if ($has_dims){
			$want = $this->item_dims_hash(['dims' => $this->dims_rows_from_map($dims_map)]);
			foreach ($masters as $item){
				if ($this->item_dims_hash($item) === $want){
					return $item;
				}
			}
			return [];
		}

		if ($product_item_id > 0){
			foreach ($masters as $item){
				if ((int)$item['cms_page_panel_id'] === $product_item_id){
					return $item;
				}
			}
		}

		if (count($masters) === 1){
			return reset($masters);
		}

		return [];

	}

	/**
	 * Held qty per master of one product (item_id => qty) on unfinished orders, own cart
	 * included unless $exclude_order_id. Empty when the type does not count stock.
	 */
	function held_qty_map($product_id, $type = null, $exclude_order_id = 0){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$product_id = (int)$product_id;
		if ($product_id < 1 || (is_array($type) && (string)($type['stock_control'] ?? 'none') !== 'count')){
			return [];
		}

		$held = [];
		foreach ($this->cms_page_panel_model->get_list('shop/order_line', ['product_id' => $product_id]) as $line){
			$item_id = (int)($line['product_item_id'] ?? 0);
			$order_id = (int)($line['order_id'] ?? 0);
			if ($item_id < 1 || $order_id < 1 || $order_id === (int)$exclude_order_id){
				continue;
			}
			// Orders load once per request (panel cache), however many lines share them.
			$order = $this->cms_page_panel_model->get_cms_page_panel($order_id);
			if (empty($order['cms_page_panel_id']) || !$this->shop_model->order_holds_stock($order)){
				continue;
			}
			$held[$item_id] = ($held[$item_id] ?? 0) + (int)$line['quantity'];
		}

		return $held;

	}

	function held_qty_for_item($item_id){

		$this->load->model('cms/cms_page_panel_model');

		$item = $this->cms_page_panel_model->get_cms_page_panel((int)$item_id);
		$held = $this->held_qty_map((int)($item['product_id'] ?? 0));
		return $held[(int)$item_id] ?? 0;

	}

	/**
	 * Listed number minus held qty. Pass $held (held_qty_map) when checking several masters.
	 */
	function master_available($item, $held = null){

		if (!is_array($held)){
			$held = $this->held_qty_map((int)($item['product_id'] ?? 0));
		}
		return (int)($item['number'] ?? 0) - ($held[(int)($item['cms_page_panel_id'] ?? 0)] ?? 0);

	}

	/**
	 * Re-check a draft cart's local item lines against stock held by other orders.
	 * All fit: refresh the cart hold. Some do not: no refresh, so a stale cart does not
	 * take back stock another cart now holds. Returns the product_item_ids that do not fit.
	 */
	function cart_refresh_hold($order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$own = [];
		$items = [];
		foreach ($this->shop_model->get_order_lines($order_id) as $line){
			$item_id = $this->shop_model->order_line_item_id($line);
			if ($item_id < 1 || $this->cart_line_is_shopify($line)){
				continue;
			}
			$items[$item_id] = $this->cms_page_panel_model->get_cms_page_panel($item_id);
			$own[$item_id] = ($own[$item_id] ?? 0) + (int)$line['quantity'];
		}

		$held_by_product = [];
		$lost = [];
		foreach ($own as $item_id => $qty){
			if (!$this->_cart_item_fits($items[$item_id], $qty, $order_id, $held_by_product)){
				$lost[] = $item_id;
			}
		}

		if (!$lost){
			$this->shop_model->touch_cart_order($order_id);
		}
		return $lost;

	}

	/**
	 * Cart add check, same rule as cart_refresh_hold: listed stock minus other orders must
	 * cover this cart's qty of the item plus $qty. Holds whether or not this cart is stale.
	 */
	function cart_add_fits($item, $qty, $order_id){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$item_id = (int)$item['cms_page_panel_id'];
		foreach ($this->shop_model->get_order_lines($order_id) as $line){
			if ($this->shop_model->order_line_item_id($line) === $item_id){
				$qty += (int)$line['quantity'];
			}
		}

		$held_by_product = [];
		return $this->_cart_item_fits($item, $qty, $order_id, $held_by_product);

	}

	function _cart_item_fits($item, $qty, $order_id, &$held_by_product){

		$product_id = (int)($item['product_id'] ?? 0);
		$product = $this->cms_page_panel_model->get_cms_page_panel($product_id);
		if (empty($product['cms_page_panel_id']) || !$this->item_is_offered($item)){
			return false;
		}
		$type = $this->resolve_product_type($product);

		$item_id = (int)$item['cms_page_panel_id'];
		$listed = false;
		foreach ($this->storefront_masters($product, $type) as $master){
			if ((int)$master['cms_page_panel_id'] === $item_id){
				$listed = true;
				break;
			}
		}
		if (!$listed){
			return false;
		}
		if ((string)$type['stock_control'] !== 'count' || $this->type_allows_negative($type)){
			return true;
		}

		if (!isset($held_by_product[$product_id])){
			$held_by_product[$product_id] = $this->held_qty_map($product_id, $type, $order_id);
		}
		return (int)$item['number'] - ($held_by_product[$product_id][$item_id] ?? 0) >= $qty;

	}

	function item_is_offered($item){

		$v = $item['availability'] ?? '1';
		if ($v === '0' || $v === 0 || $v === false || $v === 'no' || $v === 'No'){
			return false;
		}

		return true;

	}

	function item_can_sell($item, $type = null, $held = null){

		if (!$this->item_is_offered($item)){
			return false;
		}
		if (!is_array($type)){
			$type = [];
		}
		if ((string)($type['stock_control'] ?? 'none') !== 'count'){
			return true;
		}
		if ($this->type_allows_negative($type)){
			return true;
		}

		return $this->master_available($item, $held) > 0;

	}

	/**
	 * No-limit products: create a master item for every dim combination that is missing.
	 */
	function ensure_unlimited_items($product, $type = null){

		$this->load->model('cms/cms_page_panel_model');

		$product = is_array($product) ? $product : [];
		$product_id = (int)($product['cms_page_panel_id'] ?? 0);
		if ($product_id < 1){
			return;
		}
		if (!is_array($type)){
			$type = $this->resolve_product_type($product);
		}
		if ((string)($type['stock_control'] ?? 'none') !== 'none'){
			return;
		}

		$dim_panels = $this->type_dim_panels($type);
		$sets = [];
		foreach ($dim_panels as $dim){
			$did = (int)($dim['cms_page_panel_id'] ?? 0);
			$vals = [];
			foreach (($dim['values'] ?? []) as $v){
				$vid = (string)($v['id'] ?? '');
				if ($vid !== ''){
					$vals[] = $vid;
				}
			}
			if ($did < 1 || !$vals){
				return;
			}
			$sets[] = ['did' => $did, 'values' => $vals];
		}

		$combos = [[]];
		foreach ($sets as $set){
			$next = [];
			foreach ($combos as $combo){
				foreach ($set['values'] as $vid){
					$row = $combo;
					$row[$set['did']] = $vid;
					$next[] = $row;
				}
			}
			$combos = $next;
		}
		if (!$sets){
			$combos = [[]];
		}

		foreach ($combos as $map){
			if ($this->find_master_item($product_id, $map)){
				continue;
			}
			$this->create_master_item($product_id, $map);
		}

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

	/**
	 * Legacy. Storefront is shop/product via get_product_presentation.
	 * Kept for shop/productdims.
	 */
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
				'default_product_item_id' => 0,
				'selected_product_item_id' => 0,
		];

		$dim_panels = $this->type_dim_panels($type);
		$masters = $product_id > 0 ? $this->storefront_masters($product, $type) : [];

		if (!$dim_panels && !$masters){
			return $out;
		}

		$held = $this->held_qty_map($product_id, $type);
		$default = $this->default_master_item($masters, $type, $held);
		$out['default_product_item_id'] = (int)($default['cms_page_panel_id'] ?? 0);

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

			// Pickers and variants run on ids: dim panel id => value id. Labels are display only.
			$pos = 1;
			foreach ($dim_panels as $dim){
				$did = (int)$dim['cms_page_panel_id'];
				$values = [];
				foreach (($dim['values'] ?? []) as $v){
					$vid = (string)($v['id'] ?? '');
					if ($vid === '' || empty($used[$did][$vid])){
						continue;
					}
					$values[] = [
							'id' => $vid,
							'label' => (string)($v['label'] ?? ''),
					];
				}
				if (!$values){
					continue;
				}
				$out['dims'][$pos] = [
						'dim_id' => $did,
						'name' => (string)($dim['heading'] ?? ''),
						'values' => $values,
				];
				$pos++;
			}

			foreach ($masters as $item){
				$map = $this->item_dims_map($item);
				$variant = [
						'product_item_id' => (int)$item['cms_page_panel_id'],
						'shopify_variant_id' => (string)($item['shopify_variant_id'] ?? ''),
						'price' => (array_key_exists('price', $item) && $item['price'] !== '' && $item['price'] !== null) ? $item['price'] : ($product['price'] ?? ''),
						'heading' => '',
						'offered' => $this->item_is_offered($item) ? 1 : 0,
						'sellable' => $this->item_can_sell($item, $type, $held) ? 1 : 0,
						'dims' => [],
				];
				$labels = [];
				foreach ($dim_panels as $dim){
					$did = (int)$dim['cms_page_panel_id'];
					$val = (string)($map[$did] ?? '');
					$variant['dims'][$did] = $val;
					$label = (string)$this->get_dim_value_data($did, $val)['label'];
					if ($label !== ''){
						$labels[] = $label;
					}
				}
				$variant['heading'] = implode(' / ', $labels);
				$out['variants'][$item['cms_page_panel_id']] = $variant;
			}

			$default_dims = $out['variants'][$out['default_product_item_id']]['dims'] ?? [];
			$out['selected_product_item_id'] = $this->picker_state($out['dims'], $out['variants'], $default_dims);

			return $out;
		}

		// No master items. Count mode has nothing to sell. No-limit items are created on save.
		return $out;

	}

	/**
	 * Picker cascade. Mirrored in shop/js/product.js product_picker_refresh — keep both the same.
	 *
	 * Dims in page order. For dim k the candidates are available variants (offered and
	 * sellable) that match the values selected in the dims before k. A value is available
	 * when a candidate has it. A selection that is not available moves to the next
	 * available value in list order (wrapping), or to none. Sets 'available' and 'active'
	 * on every value and returns the selected product_item_id, or 0.
	 */
	function picker_state(&$dims, $variants, $selected){

		$candidates = [];
		foreach ($variants as $variant){
			if (!empty($variant['offered']) && !empty($variant['sellable'])){
				$candidates[] = $variant;
			}
		}

		foreach ($dims as $pos => $dim){
			$did = (int)$dim['dim_id'];
			$values = array_values($dim['values']);
			$count = count($values);

			$have = [];
			foreach ($candidates as $variant){
				$have[(string)($variant['dims'][$did] ?? '')] = true;
			}

			$want = (string)($selected[$did] ?? '');
			$start = -1;
			foreach ($values as $i => $value){
				if ($value['id'] === $want){
					$start = $i;
					break;
				}
			}

			// Step 0 keeps the current value when it is still available.
			$pick = '';
			for ($step = 0; $step < $count; $step++){
				$i = $start < 0 ? $step : ($start + $step) % $count;
				if (!empty($have[$values[$i]['id']])){
					$pick = $values[$i]['id'];
					break;
				}
			}

			foreach ($values as $i => $value){
				$values[$i]['available'] = !empty($have[$value['id']]) ? 1 : 0;
				$values[$i]['active'] = ($pick !== '' && $value['id'] === $pick) ? 1 : 0;
			}
			$dims[$pos]['values'] = $values;

			$next = [];
			if ($pick !== ''){
				foreach ($candidates as $variant){
					if ((string)($variant['dims'][$did] ?? '') === $pick){
						$next[] = $variant;
					}
				}
			}
			$candidates = $next;
		}

		$first = reset($candidates);
		return is_array($first) ? (int)$first['product_item_id'] : 0;

	}

	function cart_line_is_shopify($line){

		if (!empty($line['shopify_variant_id']) || !empty($line['merchandise_id'])){
			return true;
		}
		return ((string)($line['line_type'] ?? '')) === 'shopify_variant';

	}


	function dims_description($item){

		return $this->dims_description_from_map($this->item_dims_map($item));

	}

}
