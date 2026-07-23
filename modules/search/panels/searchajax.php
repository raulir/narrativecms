<?php

namespace search;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class searchajax extends \Controller{

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_search_model');
		$this->load->model('cms/cms_panel_model');

		// Frontend search settings (sources, min chars)
		$search_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('search/search');
		if (!is_array($search_settings)){
			$search_settings = [];
		}

		// searchajax settings (messages + label_page + shop/timmy extends)
		$ajax_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('search/searchajax');
		if (is_array($ajax_settings)){
			$params = array_merge($ajax_settings, $params);
		}

		$term = trim((string)($params['term'] ?? ''));
		$min_chars = (int)($search_settings['min_chars'] ?? 3);
		if ($min_chars < 1){
			$min_chars = 3;
		}

		$include_pages = (string)($search_settings['include_pages'] ?? '1') !== '0';
		$include_all_fields = (string)($search_settings['include_all_fields'] ?? '0') === '1';
		$include_lists = $this->_include_lists_set($search_settings['include_lists'] ?? []);

		$params['result'] = [];
		$params['result_products'] = [];
		$params['result_other'] = [];
		$params['error_message'] = '';

		if ($term === '' || mb_strlen($term) < $min_chars){
			$msg = (string)($params['no_characters'] ?? 'Enter {{x}} characters');
			$params['error_message'] = str_ireplace('{{x}}', (string)$min_chars, $msg);
			return $params;
		}

		$search_opts = [];
		if ($include_all_fields){
			$search_opts['all_fields'] = 1;
		}

		$result = $this->cms_search_model->get_search($term, $search_opts);
		if (!is_array($result) || empty($result['cms_pages'])){
			$result = ['cms_pages' => []];
		}

		$products = [];
		$other = [];
		$max_products = 8;
		$max_other = 6;

		foreach ($result['cms_pages'] as $page_id => $score){

			if (count($products) >= $max_products && count($other) >= $max_other){
				break;
			}

			$page_id = (string)$page_id;
			$slug = $this->cms_slug_model->get_cms_slug_by_target($page_id);

			if (stristr($page_id, '=')){

				list($panel_name, $cms_page_panel_id) = explode('=', $page_id, 2);

				if (empty($include_lists[$panel_name])){
					continue;
				}

				$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
				if (!is_array($data) || empty($data['show']) || empty($slug)){
					continue;
				}

				$row = $this->_build_list_result_row($panel_name, $cms_page_panel_id, $data, $slug, $score, $params);
				if ($row === null){
					continue;
				}

				if ($row['kind'] === 'product'){
					if (count($products) < $max_products){
						$products[] = $row;
					}
				} else if (count($other) < $max_other){
					$other[] = $row;
				}

			} else {

				if (!$include_pages || count($other) >= $max_other){
					continue;
				}

				$page_data = $this->cms_page_model->get_page($page_id);
				if (!is_array($page_data)){
					continue;
				}

				// Only main content pages (not header/footer layout pages)
				$position = (string)($page_data['position'] ?? '');
				if ($position === 'header' || $position === 'footer'){
					continue;
				}

				$lists = $this->cms_page_panel_model->get_lists();
				if (in_array($slug, $lists, true)){
					$slug = '';
				}

				if (empty($slug) || !empty($page_data['status'])){
					continue;
				}

				$heading = !empty($page_data['title']) ? $page_data['title'] : '[ no title ]';
				// title may live in meta
				if ($heading === '[ no title ]' && !empty($page_data['meta'])){
					$meta = is_string($page_data['meta']) ? json_decode($page_data['meta'], true) : $page_data['meta'];
					if (is_array($meta) && !empty($meta['title'])){
						$heading = $meta['title'];
					}
				}

				$label_page = (string)($params['label_page'] ?? 'Page');
				$other[] = [
						'kind' => 'page',
						'panel_name' => '',
						'type_label' => $label_page,
						'heading' => $heading,
						'list_heading' => $label_page.' - '.$heading,
						'image' => (!empty($page_data['image']) ? $page_data['image'] : ''),
						'page_id' => $page_id,
						'slug' => $slug,
						'score' => $score,
						'cms_page_panel_id' => 0,
						'data' => $page_data,
				];
			}

		}

		$params['result_products'] = $products;
		$params['result_other'] = $other;
		// Flat list for simple base template
		$params['result'] = array_merge($products, $other);

		return $params;

	}

	function _build_list_result_row($panel_name, $cms_page_panel_id, $data, $slug, $score, $params){

		$heading = (string)($data['heading'] ?? $data['title'] ?? '');
		$image = (string)($data['image'] ?? '');

		if ($panel_name === 'shop/product'){
			return [
					'kind' => 'product',
					'panel_name' => $panel_name,
					'type_label' => '',
					'heading' => $heading,
					'list_heading' => $heading,
					'image' => $image,
					'page_id' => $panel_name.'='.$cms_page_panel_id,
					'slug' => $slug,
					'score' => $score,
					'cms_page_panel_id' => (int)$cms_page_panel_id,
					'min_price' => $data['min_price'] ?? '',
					'max_price' => $data['max_price'] ?? '',
					'available' => $data['available'] ?? '',
					'data' => $data,
			];
		}

		$type_label = $this->_type_label_for_panel($panel_name, $data, $params);
		$list_heading = $type_label !== '' ? ($type_label.' - '.$heading) : $heading;

		return [
				'kind' => $this->_kind_for_panel($panel_name),
				'panel_name' => $panel_name,
				'type_label' => $type_label,
				'heading' => $heading,
				'list_heading' => $list_heading,
				'image' => $image,
				'page_id' => $panel_name.'='.$cms_page_panel_id,
				'slug' => $slug,
				'score' => $score,
				'cms_page_panel_id' => (int)$cms_page_panel_id,
				'data' => $data,
		];

	}

	function _kind_for_panel($panel_name){

		if ($panel_name === 'shop/category'){
			return 'category';
		}
		if ($panel_name === 'shop/subcategory'){
			return 'subcategory';
		}
		if ($panel_name === 'shop/collection'){
			return 'collection';
		}
		return 'list';

	}

	function _type_label_for_panel($panel_name, $data, $params){

		if ($panel_name === 'shop/category'){
			return (string)($params['label_category'] ?? 'Category');
		}
		if ($panel_name === 'shop/subcategory'){
			return (string)($params['label_subcategory'] ?? 'Subcategory');
		}
		if ($panel_name === 'shop/collection'){
			$type = trim((string)($data['type'] ?? ''));
			if ($type !== ''){
				return ucfirst($type);
			}
			return (string)($params['label_collection'] ?? 'Collection');
		}

		// Generic list: use list item_title when available
		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		if (!empty($config['list']['item_title'])){
			return (string)$config['list']['item_title'];
		}
		$parts = explode('/', $panel_name);
		return ucfirst(end($parts));

	}

	function _include_lists_set($rows){

		$set = [];
		if (!is_array($rows)){
			return $set;
		}
		foreach ($rows as $row){
			if (is_string($row)){
				$list = trim($row);
			} else {
				$list = trim((string)($row['list'] ?? ''));
			}
			if ($list !== ''){
				$set[$list] = true;
			}
		}
		return $set;

	}

}
