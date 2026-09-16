<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Product image compose: shop domain on top of provides.image_compose.
 * Overlay field on the product is original_artwork (Timmy/Shopify until moved to shop/product).
 * Cascade style = main/thumb image. Dimension rules (imagemaker/dimension_rules) = per-option styles.
 */
class shop_image_model extends \Model {

	const PRODUCT_COMPOSITE_MAX_MS = 15000;

	function resolve_style_id($product){

		$product = is_array($product) ? $product : [];
		$style_id = (int)($product['imagemaker_style_id'] ?? 0);
		if ($style_id > 0){
			return $style_id;
		}

		$subcategory_id = (int)($product['subcategory_id'] ?? 0);
		if ($subcategory_id <= 0){
			return 0;
		}

		$this->load->model('cms/cms_page_panel_model');
		$sub = $this->cms_page_panel_model->get_cms_page_panel($subcategory_id);
		$style_id = (int)($sub['imagemaker_style_id'] ?? 0);
		if ($style_id > 0){
			return $style_id;
		}

		$category_id = (int)($sub['category_id'] ?? 0);
		if ($category_id <= 0){
			return 0;
		}

		$cat = $this->cms_page_panel_model->get_cms_page_panel($category_id);
		return (int)($cat['imagemaker_style_id'] ?? 0);

	}

	function resolve_product_composite($product){

		$product = is_array($product) ? $product : [];
		$product_id = (int)($product['cms_page_panel_id'] ?? 0);
		$artwork = trim((string)($product['original_artwork'] ?? ''));
		if ($product_id <= 0 || $artwork === ''){
			return '';
		}

		$style_id = $this->resolve_style_id($product);
		if ($style_id <= 0){
			return '';
		}

		return $this->get_product_composite_image($product_id, $artwork, $style_id);

	}

	function apply_composite_to_images($images, $composite_rel, $opts = []){

		$composite_rel = trim((string)$composite_rel);
		if ($composite_rel === ''){
			return is_array($images) ? $images : [];
		}

		$images = is_array($images) ? $images : [];
		foreach ($images as $img){
			if (($img['image'] ?? '') === $composite_rel){
				return $images;
			}
		}

		$drop_main = trim((string)($opts['drop_main_image'] ?? ''));
		$variant = [];
		$other = [];
		foreach ($images as $img){
			if (!is_array($img)){
				continue;
			}
			if (!empty($img['ids'])){
				$variant[] = $img;
				continue;
			}
			if ($drop_main !== '' && ($img['image'] ?? '') === $drop_main){
				continue;
			}
			$other[] = $img;
		}

		$composite = [
				'image' => $composite_rel,
				'heading' => '',
		];

		return array_merge($variant, [$composite], $other);

	}

	function apply_to_product_params($params){

		if (!is_array($params) || !empty($params['error'])){
			return $params;
		}

		$images = (!empty($params['images']) && is_array($params['images'])) ? $params['images'] : [];
		$drop_main = trim((string)($params['image'] ?? ''));
		$artwork = trim((string)($params['original_artwork'] ?? ''));
		$product_id = (int)($params['cms_page_panel_id'] ?? 0);

		$extras = [];
		$covered_ids = [];
		if ($product_id > 0 && $artwork !== ''){
			foreach ($this->matching_dimension_rules($params) as $match){
				$path = $this->get_product_composite_image($product_id, $artwork, (int)$match['style_id']);
				if ($path === ''){
					continue;
				}
				$extras[] = [
						'image' => $path,
						'heading' => $match['heading'],
						'ids' => $match['variant_ids'],
				];
				foreach ($match['variant_ids'] as $vid){
					$covered_ids[(string)$vid] = true;
				}
			}
		}

		if ($covered_ids){
			$images = $this->strip_covered_variant_ids($images, array_keys($covered_ids));
		}

		$cascade = $this->resolve_product_composite($params);
		if ($cascade !== ''){
			$composite_opts = [];
			if (!$extras){
				$composite_opts['drop_main_image'] = $drop_main;
			}
			$images = $this->apply_composite_to_images($images, $cascade, $composite_opts);
		}

		// Oak/Unframed etc.: attach leftover variant ids to the cascade image so
		// the PDP picker does not fall through to the first extra (e.g. Black).
		$unmatched_ids = [];
		if ($extras && $cascade !== ''){
			foreach ($this->product_variant_ids($params) as $vid){
				if ($vid !== '' && empty($covered_ids[$vid])){
					$unmatched_ids[] = $vid;
				}
			}
			if ($unmatched_ids){
				$images = $this->strip_covered_variant_ids($images, $unmatched_ids);
				foreach ($images as $ikey => $img){
					if (!is_array($img)){
						continue;
					}
					if (($img['image'] ?? '') === $cascade){
						$images[$ikey]['ids'] = $unmatched_ids;
					}
				}
			}
		}

		if ($extras){
			$images = array_merge($extras, $images);
		}

		$params['images'] = $images;

		return $params;

	}

	function product_variant_ids($product){

		$product = is_array($product) ? $product : [];
		$product_id = (int)($product['cms_page_panel_id'] ?? 0);
		if ($product_id <= 0){
			return [];
		}
		$this->load->model('shop/shop_dim_model');
		$ids = [];
		foreach ($this->shop_dim_model->get_master_items($product_id) as $item){
			$iid = (int)($item['cms_page_panel_id'] ?? 0);
			if ($iid > 0){
				$ids[] = (string)$iid;
			}
		}

		return $ids;

	}

	function get_dimension_rules(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('imagemaker/dimension_rules');
		$rows = is_array($settings) ? ($settings['rules'] ?? []) : [];
		if (!is_array($rows)){
			return [];
		}

		$out = [];
		foreach ($rows as $row){
			if (!is_array($row)){
				continue;
			}
			$dim = strtolower(trim((string)($row['product_dimension'] ?? '')));
			$val = strtolower(trim((string)($row['dimension_value'] ?? '')));
			$sid = (int)($row['imagemaker_style_id'] ?? 0);
			if ($dim === '' || $val === '' || $sid <= 0){
				continue;
			}
			$out[] = [
					'product_dimension' => $dim,
					'dimension_value' => $val,
					'imagemaker_style_id' => $sid,
			];
		}

		return $out;

	}

	function matching_dimension_rules($product){

		$product = is_array($product) ? $product : [];
		$rules = $this->get_dimension_rules();
		$product_id = (int)($product['cms_page_panel_id'] ?? 0);
		if (!$rules || $product_id <= 0){
			return [];
		}

		$this->load->model('shop/shop_dim_model');
		$items = $this->shop_dim_model->get_master_items($product_id);
		if (!$items){
			return [];
		}

		$used = [];
		$matches = [];

		foreach ($rules as $rule){
			$ids = [];
			$heading = '';
			foreach ($items as $item){
				$iid = (string)((int)($item['cms_page_panel_id'] ?? 0));
				if ($iid === '0' || isset($used[$iid])){
					continue;
				}
				$have = $this->_item_dim_match($item, $rule['product_dimension'], $rule['dimension_value']);
				if ($have === ''){
					continue;
				}
				$ids[] = $iid;
				$used[$iid] = true;
				if ($heading === ''){
					$heading = $have;
				}
			}
			if (!$ids){
				continue;
			}
			$matches[] = [
					'style_id' => $rule['imagemaker_style_id'],
					'variant_ids' => $ids,
					'heading' => $heading !== '' ? $heading : $rule['dimension_value'],
			];
		}

		return $matches;

	}

	function strip_covered_variant_ids($images, $covered_ids){

		$images = is_array($images) ? $images : [];
		$covered = [];
		foreach ((array)$covered_ids as $id){
			$id = (string)$id;
			if ($id !== ''){
				$covered[$id] = true;
			}
		}
		if (!$covered){
			return $images;
		}

		$out = [];
		foreach ($images as $img){
			if (!is_array($img)){
				continue;
			}
			$ids = $img['ids'] ?? [];
			if (!empty($ids) && is_array($ids)){
				$keep = [];
				foreach ($ids as $id){
					if (empty($covered[(string)$id])){
						$keep[] = $id;
					}
				}
				if (!$keep){
					continue;
				}
				$img['ids'] = $keep;
			}
			$out[] = $img;
		}

		return $out;

	}

	function _item_dim_match($item, $dimension_name, $want){

		$dim_slug = strtolower(trim((string)$dimension_name));
		$want = strtolower(trim((string)$want));
		if ($dim_slug === '' || $want === '' || !is_array($item)){
			return '';
		}

		$this->load->model('shop/shop_dim_model');
		$dim = $this->shop_dim_model->get_dim_by_id_slug($dim_slug);
		$did = (int)($dim['cms_page_panel_id'] ?? 0);
		if ($did <= 0){
			return '';
		}
		$map = $this->shop_dim_model->item_dims_map($item);
		$val = strtolower(trim((string)($map[$did] ?? '')));
		if ($val === ''){
			return '';
		}
		$info = $this->shop_dim_model->get_dim_value_data($did, $val);
		$label = strtolower(trim((string)($info['label'] ?? '')));
		if ($val !== $want && $label !== $want){
			return '';
		}
		return $label !== '' ? $label : $val;

	}

	function product_composite_cache_key($product_id, $original_artwork, $style_update_time, $style_id = 0){

		$product_id = (int)$product_id;
		$style_id = (int)$style_id;
		$name = basename(str_replace('\\', '/', (string)$original_artwork));
		$hash8 = substr(md5($name.'.'.$style_id.'.'.(string)$style_update_time.'.keep_alpha'), 0, 8);
		$rel = 'imagemaker/product_'.$product_id.'_'.$style_id.'_'.$hash8.'.png';
		return ['hash8' => $hash8, 'rel' => $rel];

	}

	function get_product_composite_image($product_id, $original_artwork, $style_id){

		$this->load->model('shop/shop_model');
		$provider = $this->shop_model->get_image_compose_panel();
		if ($provider === ''){
			return '';
		}

		$product_id = (int)$product_id;
		$style_id = (int)$style_id;
		$original_artwork = trim((string)$original_artwork);
		if ($product_id <= 0 || $style_id <= 0 || $original_artwork === ''){
			return '';
		}

		$this->load->model('cms/cms_page_panel_model');
		$style = $this->cms_page_panel_model->get_cms_page_panel($style_id);
		if (empty($style) || empty($style['cms_page_panel_id'])){
			return '';
		}

		$print_background = trim((string)($style['print_background'] ?? ''));
		$transform = $style['transform'] ?? '';
		if ($print_background === '' || $transform === '' || $transform === null){
			return '';
		}

		$style_update_time = (int)($style['update_time'] ?? 0);
		if ($style_update_time <= 0){
			$style_update_time = (int)($style['create_time'] ?? 0);
		}

		$cache = $this->product_composite_cache_key($product_id, $original_artwork, $style_update_time, $style_id);
		$rel = $cache['rel'];
		$abs = $this->_upload_absolute($rel);

		$file_ok = is_file($abs);
		$db_ok = $this->product_composite_record_ok($rel);
		if ($file_ok && $db_ok){
			return $rel;
		}
		if ($file_ok || $db_ok){
			$this->purge_product_composite($rel);
		}

		if ($this->script_elapsed_ms() >= self::PRODUCT_COMPOSITE_MAX_MS){
			error_log_user('CMS error [shop/image_compose]: skip generate (timer) product '.$product_id);
			return '';
		}

		$CI = function_exists('get_instance') ? get_instance() : null;
		if ($CI === null || !method_exists($CI, 'run_action')){
			error_log_user('CMS error [shop/image_compose]: no controller for provider call');
			return '';
		}

		$result = $CI->run_action($provider, [
				'do' => 'compose',
				'overlay' => $original_artwork,
				'base' => $print_background,
				'transform' => $transform,
				'blending' => $style['blending'] ?? 'on',
				'return_result' => 1,
				'no_html' => 1,
		]);
		if (!is_array($result)){
			error_log_user('CMS error [shop/image_compose]: provider failed product '.$product_id);
			return '';
		}
		$src_rel = trim((string)($result['image'] ?? ''));
		$reason = trim((string)($result['_reason'] ?? ''));
		if ($src_rel === '' || $reason !== ''){
			if ($reason === ''){
				$reason = 'empty image';
			}
			error_log_user('CMS error [shop/image_compose]: '.$reason.' product '.$product_id);
			return '';
		}

		$src_abs = $this->_upload_absolute($src_rel);
		if (!is_file($src_abs)){
			error_log_user('CMS error [shop/image_compose]: missing file '.$src_rel);
			return '';
		}

		$this->_ensure_imagemaker_dir();
		if ($src_abs !== $abs){
			if (!@copy($src_abs, $abs)){
				error_log_user('CMS error [shop/image_compose]: copy failed product '.$product_id);
				return '';
			}
		}

		if (!is_file($abs)){
			return '';
		}

		if (!$this->register_product_composite_cms_image($rel)){
			return $rel;
		}

		return $rel;

	}

	function script_elapsed_ms(){

		$start = $GLOBALS['timer']['start'] ?? null;
		if ($start === null || $start === ''){
			return 0;
		}
		return (int)round(microtime(true) * 1000) - (int)$start;

	}

	function product_composite_record_ok($rel){

		$rel = str_replace('\\', '/', ltrim((string)$rel, '/'));
		if ($rel === ''){
			return false;
		}

		$this->load->model('cms/cms_image_model');
		$row = $this->cms_image_model->get_cms_image_by_filename($rel);
		return !empty($row['cms_image_id']);

	}

	function purge_product_composite($rel){

		$rel = str_replace('\\', '/', ltrim((string)$rel, '/'));
		if ($rel === ''){
			return;
		}

		$this->load->model('cms/cms_image_model');
		$this->cms_image_model->delete_cms_image_by_filename($rel, true);

		$abs = $this->_upload_absolute($rel);
		if (is_file($abs)){
			@unlink($abs);
		}

		if (!empty($GLOBALS['cache']['images_by_filename'][$rel])){
			unset($GLOBALS['cache']['images_by_filename'][$rel]);
		}

	}

	function register_product_composite_cms_image($rel){

		$rel = str_replace('\\', '/', ltrim((string)$rel, '/'));
		$abs = $this->_upload_absolute($rel);
		if ($rel === '' || !is_file($abs)){
			return false;
		}

		$this->load->model('cms/cms_image_model');
		$row = $this->cms_image_model->get_cms_image_by_filename($rel);
		if (!empty($row['cms_image_id'])){
			return true;
		}

		$base = basename($rel);
		$created = $this->cms_image_model->create_cms_image('imagemaker/', $base, 'imagemaker');
		$filename = is_array($created) ? (string)($created['filename'] ?? '') : (string)$created;
		if ($filename === '' || $filename !== $rel){
			if ($filename !== '' && $filename !== $rel){
				$this->cms_image_model->delete_cms_image_by_filename($filename, true);
			}
			return false;
		}

		if (!empty($GLOBALS['cache']['images_by_filename'][$rel])){
			unset($GLOBALS['cache']['images_by_filename'][$rel]);
		}
		$row = $this->cms_image_model->get_cms_image_by_filename($rel);
		return !empty($row['cms_image_id']);

	}

	function invalidate_thumbs_for_style($style_id){

		$style_id = (int)$style_id;
		if ($style_id <= 0){
			return;
		}

		$this->_invalidate_product_thumbs($this->product_ids_resolving_to_style($style_id));

	}

	function invalidate_thumbs_for_subcategory($subcategory_id){

		$subcategory_id = (int)$subcategory_id;
		if ($subcategory_id <= 0){
			return;
		}

		$pids = $this->_ids_for_panel_param('shop/product', 'subcategory_id', $subcategory_id);
		$this->_invalidate_product_thumbs($this->_products_without_own_style($pids));

	}

	function invalidate_thumbs_for_category($category_id){

		$category_id = (int)$category_id;
		if ($category_id <= 0){
			return;
		}

		$subs = $this->_ids_for_panel_param('shop/subcategory', 'category_id', $category_id);
		foreach ($subs as $sid){
			if ($this->_panel_param_int($sid, 'imagemaker_style_id') > 0){
				continue;
			}
			$this->invalidate_thumbs_for_subcategory($sid);
		}

	}

	function product_ids_resolving_to_style($style_id){

		$style_id = (int)$style_id;
		if ($style_id <= 0){
			return [];
		}

		$ids = $this->_ids_for_panel_param('shop/product', 'imagemaker_style_id', $style_id);

		$subs_with_style = $this->_ids_for_panel_param('shop/subcategory', 'imagemaker_style_id', $style_id);
		$via_sub = [];
		foreach ($subs_with_style as $sid){
			foreach ($this->_ids_for_panel_param('shop/product', 'subcategory_id', $sid) as $pid){
				$via_sub[] = $pid;
			}
		}
		$ids = array_merge($ids, $this->_products_without_own_style($via_sub));

		$cats_with_style = $this->_ids_for_panel_param('shop/category', 'imagemaker_style_id', $style_id);
		$subs_in_cat = [];
		foreach ($cats_with_style as $cid){
			foreach ($this->_ids_for_panel_param('shop/subcategory', 'category_id', $cid) as $sid){
				if ($this->_panel_param_int($sid, 'imagemaker_style_id') > 0){
					continue;
				}
				$subs_in_cat[] = $sid;
			}
		}
		$via_cat = [];
		foreach ($subs_in_cat as $sid){
			foreach ($this->_ids_for_panel_param('shop/product', 'subcategory_id', $sid) as $pid){
				$via_cat[] = $pid;
			}
		}
		$ids = array_merge($ids, $this->_products_without_own_style($via_cat));

		$ids = array_values(array_unique(array_map('intval', $ids)));
		return array_values(array_filter($ids, function($id){
			return $id > 0;
		}));

	}

	function _invalidate_product_thumbs($product_ids){

		if (!is_array($product_ids) || empty($product_ids)){
			return;
		}
		if (!in_array('shopify', $GLOBALS['config']['modules'] ?? [], true)){
			return;
		}

		$this->load->model('shopify/shopify_product_model');
		foreach ($product_ids as $pid){
			$pid = (int)$pid;
			if ($pid > 0){
				$this->shopify_product_model->invalidate_product_display_cache($pid);
			}
		}

	}

	function _ids_for_panel_param($panel_name, $param_name, $value){

		$panel_name = trim((string)$panel_name);
		$param_name = trim((string)$param_name);
		if ($panel_name === '' || $param_name === ''){
			return [];
		}

		$sql = 'select distinct p.cms_page_panel_id from cms_page_panel p '.
				'join cms_page_panel_param x on p.cms_page_panel_id = x.cms_page_panel_id '.
				'where p.panel_name = ? and x.name = ? and x.value = ? ';
		$query = $this->db->query($sql, [$panel_name, $param_name, (string)$value]);
		if (!$query || !$query->num_rows()){
			return [];
		}

		$ids = [];
		foreach ($query->result_array() as $row){
			$id = (int)($row['cms_page_panel_id'] ?? 0);
			if ($id > 0){
				$ids[] = $id;
			}
		}

		return $ids;

	}

	function _panel_param_int($cms_page_panel_id, $param_name){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$param_name = trim((string)$param_name);
		if ($cms_page_panel_id <= 0 || $param_name === ''){
			return 0;
		}

		$sql = 'select value from cms_page_panel_param where cms_page_panel_id = ? and name = ? limit 1 ';
		$query = $this->db->query($sql, [$cms_page_panel_id, $param_name]);
		if (!$query || !$query->num_rows()){
			return 0;
		}

		return (int)($query->row_array()['value'] ?? 0);

	}

	function _products_without_own_style($product_ids){

		if (!is_array($product_ids) || empty($product_ids)){
			return [];
		}

		$ids = [];
		foreach ($product_ids as $pid){
			$pid = (int)$pid;
			if ($pid > 0){
				$ids[$pid] = true;
			}
		}
		if (empty($ids)){
			return [];
		}

		$in = implode(',', array_keys($ids));
		$sql = 'select cms_page_panel_id, value from cms_page_panel_param '.
				'where name = ? and cms_page_panel_id in ('.$in.') ';
		$query = $this->db->query($sql, ['imagemaker_style_id']);
		if ($query){
			foreach ($query->result_array() as $row){
				if ((int)($row['value'] ?? 0) > 0){
					unset($ids[(int)$row['cms_page_panel_id']]);
				}
			}
		}

		return array_keys($ids);

	}

	function _upload_absolute($relative){

		$relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim((string)$relative, '/\\'));
		return rtrim($GLOBALS['config']['upload_path'], '/\\').DIRECTORY_SEPARATOR.$relative;

	}

	function _ensure_imagemaker_dir(){

		$dir = rtrim($GLOBALS['config']['upload_path'], '/\\').DIRECTORY_SEPARATOR.'imagemaker';
		if (!is_dir($dir)){
			@mkdir($dir, 0755, true);
		}

	}

}
