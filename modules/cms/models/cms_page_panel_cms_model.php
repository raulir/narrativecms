<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CMS admin page-panel operations (editor save, lists discovery, FK, translation write helpers).
 * May use cms_page_panel_model; FE runtime must not load this (except FE translate surfaces).
 * Ticket #763.
 */
class cms_page_panel_cms_model extends \Model {

	function __construct(){

		// Base Model has no constructor — do not call parent::__construct()
		$this->load->model('cms/cms_page_panel_model');
		// Ensure language model is available for translation helpers
		$this->cms_page_panel_model->_ensure_language_model();

	}

	/**
	 * Lazy list-model access (sort helpers used by save/copy).
	 */
	function _list_model(){

		if (empty($this->cms_page_panel_list_model)){
			$this->load->model('cms/cms_page_panel_list_model');
		}
		return $this->cms_page_panel_list_model;

	}

	function is_list_slug($slug){

		if ($slug === '' || $slug === null){
			return false;
		}

		// Namespaced list template slugs only: shop_product → shop/product
		$this->load->model('cms/cms_page_model');
		$panel_from_slug = $this->cms_page_model->list_template_panel_from_slug($slug);
		if ($panel_from_slug === ''){
			return false;
		}

		// Any real list type (including non-linkable) — leftover shells still classify as list pages
		$this->load->model('cms/cms_page_panel_model');
		$lists = $this->cms_page_panel_model->get_lists();
		return !empty($lists[$panel_from_slug]);

	}



	function reindex_search_weights_for_panel_name($panel_name){

		$search_params = $this->cms_page_panel_model->get_search_params_for_panel_name($panel_name);
		if (empty($search_params) || !is_array($search_params)){
			return 0;
		}

		$updated = 0;
		foreach ($search_params as $field => $weight){
			if (is_array($weight)){
				// repeater fields: name like images.000000.image — skip bulk SQL for nested
				continue;
			}
			$weight = (int)$weight;
			if ($weight < 1){
				continue;
			}
			$sql = "update cms_page_panel_param p ".
					"inner join cms_page_panel b on b.cms_page_panel_id = p.cms_page_panel_id ".
					"set p.search = ? ".
					"where b.panel_name = ? and p.name = ? and p.language = '' ";
			$this->db->query($sql, [$weight, $panel_name, $field]);
			$updated += (int)$this->db->affected_rows();
		}

		return $updated;

	}
	
	/**
	 * get previous and next item in list
	 */

	function refresh_all_cached_titles(){

		$query = $this->db->query('select cms_page_panel_id from cms_page_panel order by cms_page_panel_id');
		$count = 0;

		foreach($query->result_array() as $row){
			$this->cms_page_panel_model->_refresh_cached_title($row['cms_page_panel_id']);
			$count++;
		}

		return $count;

	}

	function new_cms_page_panel(){
		
		$sql = "select max(sort) as sort from cms_page_panel";
    	$query = $this->db->query($sql);
    	$result = $query->row_array();
		
		return array(
			'cms_page_panel_id' => 0,
			'block_id' => 0,
			'cms_page_id' => 0,
			'parent_id' => 0,
			'show' => 0,
			'sort' => $result['sort'] + 1,
			'title' => 'New block',
			'panel_name' => '',
			'submenu_anchor' => '',
			'submenu_title' => '',
		);
		
	}

	function restore_panel_settings_defaults($panel_name, $merge_existing = true) {

		$this->load->model('cms/cms_panel_model');
		$defaults = $this->cms_panel_model->get_settings_defaults($panel_name);
		if (empty($defaults)) {
			return false;
		}

		$settings_rows = $this->cms_page_panel_model->get_cms_page_panels_by([
			'panel_name' => $panel_name,
			'cms_page_id' => 0,
			'parent_id' => 0,
			'sort' => 0,
		]);
		if (empty($settings_rows[0]['cms_page_panel_id'])) {
			return false;
		}

		$cms_page_panel_id = (int)$settings_rows[0]['cms_page_panel_id'];
		$params = $defaults;

		if ($merge_existing) {
			$existing = $this->cms_page_panel_model->get_cms_page_panel_params($cms_page_panel_id);
			if (is_array($existing)) {
				foreach ($defaults as $key => $value) {
					if (!array_key_exists($key, $existing) || $existing[$key] === '' || $existing[$key] === null) {
						$params[$key] = $value;
					} else {
						$params[$key] = $existing[$key];
					}
				}
			}
		}

		$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, ['panel_params' => $params], true);

		return true;

	}

	/**
	 * Opt-in (#114): when panel definition has ensure_data, fill missing top-level fields
	 * from existing DB values, then definition defaults / type fallbacks.
	 * Does not overwrite keys present in $new_params (including intentional "").
	 *
	 * @param array $context cms_page_id, parent_id, sort for item vs settings structure
	 */


	function save_orders($orders){
		
		foreach($orders as $name => $value){
    		$sql = "update cms_page_panel set sort = ? where cms_page_panel_id = ? ";
	    	$this->db->query($sql, array($value, $name, ));
		}
    	
	}

	function get_page_panel_data_filenames($structure, $data){
	
		$return = [];
	
		foreach ($structure as $field){
	
			if ($field['type'] == 'file' && !empty($data[$field['name']])){
	
				$return[] = $data[$field['name']];
	
			} else if ($field['type'] == 'repeater' && !empty($data[$field['name']])){
					
				foreach($field['fields'] as $r_field){
					if ($r_field['type'] == 'file'){
						foreach($data[$field['name']] as $r_value){
							if (!empty($r_value[$r_field['name']])){
								$return[] = $r_value[$r_field['name']];
							}
						}
					}
				}
					
			}
	
		}
	
		return $return;
	
	}

	/**
	 * Unlink upload files present in $old_data but not in $new_data (or all of $old_data when $new_data is null).
	 */

	function delete_orphan_upload_files($structure, $old_data, $new_data = null){

		$old_filenames = $this->get_page_panel_data_filenames($structure, $old_data);
		if ($new_data === null){
			$filenames_diff = $old_filenames;
		} else {
			$new_filenames = $this->get_page_panel_data_filenames($structure, $new_data);
			$filenames_diff = array_diff($old_filenames, $new_filenames);
		}

		// File inputs: CMS files in the data folder
		$this->load->model('cms/cms_file_model');
		foreach($filenames_diff as $filename){
			$path = $this->cms_file_model->file_path($filename);
			if ($path !== '' && is_file($path)){
				unlink($path);
			}
		}

	}

	/**
	 * Type and place of a stored block (panel_name, cms_page_id, parent_id, sort); [] when there is none.
	 */

	function get_block_place($block_id){

		$sql = 'select panel_name, cms_page_id, parent_id, sort from cms_page_panel where cms_page_panel_id = ? limit 1 ';
		$query = $this->db->query($sql, [(int)$block_id]);

		return $query->num_rows() ? $query->row_array() : [];

	}

	/**
	 * A block's place from the request: the page and the parent must be stored, and the parent field (the
	 * parent's list the new block is added to) must be a cms_page_panels field of the parent's edit structure.
	 * Returns '' when the place is fine, otherwise the reason.
	 */

	function check_block_place($cms_page_id, $parent_id, $parent_name = ''){

		if ((int)$cms_page_id > 0){
			$query = $this->db->query('select cms_page_id from cms_page where cms_page_id = ? limit 1 ', [(int)$cms_page_id]);
			if (!$query->num_rows()){
				return 'Page not found';
			}
		}

		$parent = (int)$parent_id > 0 ? $this->get_block_place($parent_id) : [];
		if ((int)$parent_id > 0 && !$parent){
			return 'Parent block not found';
		}

		$parent_name = (string)$parent_name;
		if ($parent_name === ''){
			return '';
		}

		if (!$parent){
			return 'Unknown parent field';
		}

		$this->load->model('cms/cms_panel_model');
		$this->load->helper('cms/cms_fields_helper');
		$structure = $this->cms_panel_model->get_cms_panel_edit_structure(
				$this->cms_panel_model->get_cms_panel_config($parent['panel_name']),
				$parent['cms_page_id'], $parent['parent_id'], $parent['sort']);

		foreach ($structure as $struct){
			if (is_array($struct) && (string)($struct['name'] ?? '') === $parent_name
					&& cms_input_type_alias((string)($struct['type'] ?? '')) === 'cms_page_panels'){
				return '';
			}
		}

		return 'Unknown parent field';

	}

	/**
	 * A block's panel cache times for the caching popup and action: seconds => label.
	 * 0 is the site default (config panel_cache), -1 is no caching.
	 */

	function get_caching_options(){

		$panel_cache = (int)($GLOBALS['config']['panel_cache'] ?? 0);

		if ($panel_cache == 0){
			$global_cache = 'no caching';
		} elseif ($panel_cache < 10000){
			$global_cache = round($panel_cache / 60).' minutes';
		} elseif ($panel_cache > 1000000){
			$global_cache = '3 months';
		} else {
			$global_cache = round($panel_cache / 3600).' hours';
		}

		return [
			'0' => 'Default ('.$global_cache.')',
			'-1' => 'No caching',
			'180' => '3 minutes',
			'900' => '15 minutes',
			'7200' => '2 hours',
			'86400' => '24 hours',
		];

	}

	/**
	 * Posted caching settings for a block: a stored block, one of get_caching_options(), and list panel names
	 * from get_lists(). Returns '' when they are fine, otherwise the reason.
	 */

	function check_caching($target_id, $caching, $lists){

		if (!$this->get_block_place($target_id)){
			return 'Block not found';
		}

		if (!is_scalar($caching) || !array_key_exists((string)$caching, $this->get_caching_options())){
			return 'Unknown cache time';
		}

		if (!is_array($lists)){
			return 'Unknown list';
		}

		$this->load->model('cms/cms_page_panel_model');
		$known = $this->cms_page_panel_model->get_lists();
		foreach ($lists as $list){
			if (!is_string($list) || !isset($known[$list])){
				return 'Unknown list';
			}
		}

		return '';

	}

	/**
	 * The target of a grid cell (cms/cms_grid_editable), for both the cell write and the cell read.
	 * With $ds: the base block must be stored and have a grid field with that ds (the ds handler checks the
	 * column itself). Without: the item must be a stored block, and $name a plain field of its edit structure
	 * (not a grid, its log, a repeater, a block column or a _ key).
	 * Returns ['block_id', 'panel_name', 'ds', 'name'], or ['error' => reason].
	 */

	function get_grid_cell_target($item_id, $name, $base_id = 0, $ds = ''){

		if (!is_scalar($name) || !is_scalar($ds) || (string)$name === ''){
			return ['error' => 'Unknown field'];
		}
		$name = (string)$name;
		$ds = (string)$ds;

		$block_id = $ds !== '' ? (int)$base_id : (int)$item_id;
		$place = $block_id > 0 ? $this->get_block_place($block_id) : [];
		if (!$place){
			return ['error' => 'Block not found'];
		}

		$this->load->model('cms/cms_panel_model');
		$structure = $this->cms_panel_model->get_cms_panel_edit_structure(
				$this->cms_panel_model->get_cms_panel_config($place['panel_name']),
				$place['cms_page_id'], $place['parent_id'], $place['sort']);

		$target = ['block_id' => $block_id, 'panel_name' => $place['panel_name'], 'ds' => $ds, 'name' => $name];

		if ($ds !== ''){
			foreach ($structure as $struct){
				if (is_array($struct) && ($struct['type'] ?? '') === 'grid' && (string)($struct['ds'] ?? '') === $ds){
					return $target;
				}
			}
			return ['error' => 'Unknown grid'];
		}

		if (!array_key_exists($name, $this->filter_posted_panel_params([$name => ''], $structure))){
			return ['error' => 'Unknown field'];
		}
		foreach ($structure as $struct){
			if (is_array($struct) && (string)($struct['name'] ?? '') === $name){
				return in_array($struct['type'] ?? '', ['grid', 'repeater'], true) ? ['error' => 'Unknown field'] : $target;
			}
		}

		// A grid's {name}_log
		return ['error' => 'Unknown field'];

	}

	/**
	 * The posted panel_params that the edit structure has fields for: each field's name, each repeater's
	 * row field names, and each grid's {name}_log. Field names starting with _ and block column names are
	 * never taken (the server adds _template_page_id, _js, _css and _images itself). Below a field, its value
	 * is the input's own (a link posts [_value]).
	 */

	function filter_posted_panel_params($posted, $structure){

		if (!is_array($posted) || !is_array($structure)){
			return [];
		}

		$columns = ['cms_page_panel_id', 'cms_page_id', 'parent_id', 'sort', 'show', 'title', 'panel_name',
				'submenu_anchor', 'submenu_title', 'panel_params', 'search_params', 'translate_params',
				'create_cms_user_id', 'create_time', 'update_cms_user_id', 'update_time'];

		$allowed = [];

		foreach ($structure as $struct){

			$name = is_array($struct) ? (string)($struct['name'] ?? '') : '';

			if ($name === '' || $name[0] === '_' || in_array($name, $columns, true)){
				continue;
			}

			$allowed[$name] = $struct;

			if (($struct['type'] ?? '') === 'grid'){
				$allowed[$name.'_log'] = ['type' => 'grid_log'];
			}

		}

		$return = [];

		foreach ($posted as $key => $value){

			$key = (string)$key;

			if (!isset($allowed[$key])){
				continue;
			}

			// Repeater rows are posted per field: [field][row]
			if (($allowed[$key]['type'] ?? '') === 'repeater' && is_array($value)){

				$row_fields = [];
				foreach (($allowed[$key]['fields'] ?? []) as $r_struct){
					$r_name = is_array($r_struct) ? (string)($r_struct['name'] ?? '') : '';
					if ($r_name !== '' && $r_name[0] !== '_'){
						$row_fields[$r_name] = 1;
					}
				}

				foreach (array_keys($value) as $r_key){
					if (!isset($row_fields[(string)$r_key])){
						unset($value[$r_key]);
					}
				}

			}

			$return[$key] = $value;

		}

		return $return;

	}

	/**
	 * Build admin form payload for save / title preview.
	 * $input is a plain array (panel maps POST); not request-bound. Only the edit structure's fields are
	 * taken from panel_params (filter_posted_panel_params). A stored block keeps its stored type and place;
	 * a new block takes them from $input.
	 *
	 * @return array{data: array, data_merged: array, panel_config: array, panel_structure: array, error?: string}
	 */

	function build_panel_data_for_save($input, $language){

		$this->load->model('cms/cms_panel_model');

		$block_id = !empty($input['cms_page_panel_id']) ? (int)$input['cms_page_panel_id'] : 0;

		if ($block_id){
			$place = $this->get_block_place($block_id);
			if (!$place){
				return ['error' => 'Block not found'];
			}
		} else {
			$place = [
				'panel_name' => (string)($input['panel_name'] ?? ''),
				'cms_page_id' => (int)($input['cms_page_id'] ?? 0),
				'parent_id' => (int)($input['parent_id'] ?? 0),
				'sort' => (int)($input['sort'] ?? 0),
			];
			if (!preg_match('#^[a-z0-9_]+/[a-z0-9_]+$#', $place['panel_name'])){
				return ['error' => 'Unknown panel type'];
			}
		}

		$data = [];
		$data['cms_page_id'] = $place['cms_page_id'];
		$data['parent_id'] = $place['parent_id'];
		$data['sort'] = $place['sort'];
		$data['title'] = $input['title'] ?? null;
		$data['submenu_anchor'] = $input['submenu_anchor'] ?? null;
		$data['panel_name'] = $place['panel_name'];

		$panel_config = $this->cms_panel_model->get_cms_panel_config($data['panel_name']);

		$panel_structure = $this->cms_panel_model->get_cms_panel_edit_structure(
				$panel_config, $data['cms_page_id'], $data['parent_id'], $data['sort']);

		$data['panel_params'] = $this->filter_posted_panel_params($input['panel_params'] ?? [], $panel_structure);

		if (!empty($panel_config['list']['templates'])){
			$data['panel_params']['_template_page_id'] = $input['_template_page_id'] ?? null;
		}

		if (!empty($panel_config['list']['search_time_extra']) && is_array($panel_config['list']['search_time_extra'])
				&& !empty($data['panel_params']['date'])){
			$data['panel_params']['_search_time_extra'] = serialize($panel_config['list']['search_time_extra']);
			$data['panel_params']['_search_time_timestamp_day'] = strtotime($data['panel_params']['date'])/86400;
		}

		if (!empty($panel_config['js']) && is_array($panel_config['js'])){
			foreach($panel_config['js'] as $_js){
				list($_js_module, $_js_panel) = explode('/', $_js);
				$data['panel_params']['_js'][] = 'modules/'.$_js_module.'/js/'.$_js_panel.'.js';
			}
		}
		if (!empty($panel_config['css']) && is_array($panel_config['css'])){
			foreach($panel_config['css'] as $_css){
				list($_css_module, $_css_panel) = explode('/', $_css);
				$data['panel_params']['_css'][] = 'modules/'.$_css_module.'/css/'.$_css_panel.'.scss';
			}
		}

		$data['search_params'] = [];
		$data['translate_params'] = [];

		foreach($panel_structure as $struct){
			if (!empty($struct['search'])){
				$data['search_params'][$struct['name']] = $struct['search'];
			}
			if (!empty($struct['translate'])){
				$data['translate_params'][$struct['name']] = $language;
			}
			if ($struct['type'] == 'repeater'){
				foreach ($struct['fields'] as $r_struct){
					if (!empty($r_struct['search'])){
						$data['search_params'][$struct['name']][$r_struct['name']] = $r_struct['search'];
					}
					if (!empty($r_struct['translate'])){
						$data['translate_params'][$struct['name']][$r_struct['name']] = $language;
					}
				}
			}
		}

		foreach($panel_structure as $struct){

			if ($struct['type'] == 'image'){
				if (!empty($struct['meta']) && $struct['meta'] == 'image' && !empty($data['panel_params'][$struct['name']])){
					$data['panel_params']['_images'][] = $data['panel_params'][$struct['name']];
				}
			}

			if ($struct['type'] == 'repeater'){
				foreach ($struct['fields'] as $r_struct){
					if ($r_struct['type'] == 'image'){
						if (!empty($r_struct['meta']) && $r_struct['meta'] == 'image' && !empty($data['panel_params'][$struct['name']])){
							if (empty($data['panel_params']['_images'])){
								$data['panel_params']['_images'] = [];
							}
							array_merge($data['panel_params']['_images'], $data['panel_params'][$struct['name']]);
						}
					}
				}
			}

		}

		foreach ($data['panel_params'] as $key => $value){

			if (is_array($value) && is_array(reset($value))){
				$temp_result = [];
				foreach($value as $skey => $kvalues){
					foreach ($kvalues as $nkey => $nvalue){
						if (!is_array($nvalue)){
							if (empty($temp_result[$nkey])){
								$temp_result[$nkey] = [];
							}
							$temp_result[$nkey][$skey] = $nvalue;
						} else {
							foreach($nvalue as $nnkey => $nnvalue){
								if (empty($temp_result[$nnkey][$skey])){
									$temp_result[$nnkey][$skey] = [];
								}
								$temp_result[$nnkey][$skey][$nkey] = $nnvalue;
							}
						}
					}
				}
				$data['panel_params'][$key] = $temp_result;
			}

		}

		foreach($panel_structure as $struct){

			if ($struct['type'] == 'cms_page_panels' && empty($data['panel_params'][$struct['name']])){
				$data['panel_params'][$struct['name']] = [];
			}

			if ($struct['type'] == 'repeater' && empty($data['panel_params'][$struct['name']])){
				$data['panel_params'][$struct['name']] = [];
			}

			if ($struct['type'] == 'grid' && !empty($struct['ds']) && empty($data['panel_params'][$struct['name']]) && $block_id){
				$existing = $this->cms_page_panel_model->get_cms_page_panel($block_id, $language, false);
				if (!empty($existing[$struct['name']]) && is_array($existing[$struct['name']])){
					$data['panel_params'][$struct['name']] = $existing[$struct['name']];
				}
			}

		}

		$data_merged = $data;
		unset($data_merged['panel_params']);
		$data_merged = array_merge($data['panel_params'], $data_merged);
		$data_merged['cms_page_panel_id'] = $block_id;

		return [
			'data' => $data,
			'data_merged' => $data_merged,
			'panel_config' => $panel_config,
			'panel_structure' => $panel_structure,
		];

	}

	/**
	 * List-item title from definition + row (false when not a list item).
	 */

	function compile_list_item_title($data_merged, $panel_config, $block_id, $language){

		if (empty($panel_config['list']) || $data_merged['cms_page_id'] != 0 || empty($data_merged['sort'])){
			return false;
		}

		$title_row = $this->cms_page_panel_model->get_list_item_title_row($block_id, $data_merged, $language);

		if (!is_array($title_row)){
			return false;
		}

		return $this->cms_page_panel_model->get_list_item_title($title_row);

	}

	/**
	 * Create or update from admin form data; list slug + parent children list.
	 *
	 * @param array $options panel_config, parent_name (optional), old_data (optional, for slug hide state)
	 * @return array{cms_page_panel_id: int}
	 */

	function save_cms_page_panel_admin($block_id, $data_merged, $options = []){

		$panel_config = $options['panel_config'] ?? [];
		$parent_name = $options['parent_name'] ?? '';
		$old_data = $options['old_data'] ?? [];

		if ($block_id){

			$this->cms_page_panel_model->update_cms_page_panel($block_id, $data_merged, true);

		} else {

			$block_id = $this->cms_page_panel_model->create_cms_page_panel($data_merged);

			if (!empty($panel_config['list']['new_first'])){
				$this->_list_model()->move_first($block_id);
			}

		}

		if (!empty($panel_config['list']['link_target'])){

			$this->load->model('cms/cms_slug_model');

			$title_row = $this->cms_page_panel_model->get_list_item_title_row($block_id);
			$list_title = is_array($title_row) ? $this->cms_page_panel_model->get_list_item_title($title_row) : '';

			if (!empty($list_title)){
				$slug_string = $list_title;
			} else if (!empty($data_merged['title'])){
				$slug_string = $data_merged['title'];
			} else if (!empty($data_merged['heading'])){
				$slug_string = $data_merged['heading'];
			} else {
				$slug_string = $data_merged['panel_name'].' '.$block_id;
			}

			$slug = $this->cms_slug_model->generate_list_item_slug($data_merged['panel_name'].'='.$block_id, $slug_string);

			$this->cms_slug_model->set_page_slug(
					$data_merged['panel_name'].'='.$block_id,
					$slug,
					empty($old_data['show']) ? '1' : '0');

		}

		if (!empty($data_merged['parent_id']) && !empty($parent_name)){

			$parent = $this->cms_page_panel_model->get_cms_page_panel($data_merged['parent_id']);

			if (empty($parent[$parent_name])){
				$field_data = [];
			} else {
				if (!is_array($parent[$parent_name])){
					$field_data = explode(',', $parent[$parent_name]);
				} else {
					$field_data = $parent[$parent_name];
				}
			}

			if (!in_array($block_id, $field_data)){
				$field_data[] = $block_id;
				$field_data = array_values($field_data);
				$this->cms_page_panel_model->update_cms_page_panel($data_merged['parent_id'], [$parent_name => $field_data, ]);
			}

		}

		return ['cms_page_panel_id' => $block_id];

	}

	/**
	 * Set show flag and update page visibility or list-item slug status.
	 *
	 * @return array{show: int, block: array}
	 */

	function set_cms_page_panel_show($cms_page_panel_id, $show){

		$show = !empty($show) ? 1 : 0;

		$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);

		$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, ['show' => $show, ]);

		if (!empty($block['cms_page_id'])){
			$this->load->model('cms/cms_page_model');
			$this->cms_page_model->update_page_visibility($block['cms_page_id']);
		} else {
			$this->load->model('cms/cms_slug_model');
			$this->cms_slug_model->update_slug_status($block['panel_name'].'='.$cms_page_panel_id, empty($show) ? 1 : 0);
		}

		return ['show' => $show, 'block' => $block];

	}

	/**
	 * Deep-copy a panel including nested cms_page_panels children.
	 *
	 * @return int new cms_page_panel_id
	 */

	function copy_cms_page_panel($cms_page_panel_id){

		$this->load->model('cms/cms_panel_model');

		$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		$panel_structure = $this->cms_panel_model->get_cms_panel_definition($data['panel_name']);

		$data['show'] = 0;
		$data['title'] = 'Copy of '.$data['title'];
		if (!empty($data['heading'])){
			$data['heading'] = 'Copy of '.$data['heading'];
		}

		$all_children = [];
		foreach($panel_structure as $struct){
			if ($struct['type'] == 'cms_page_panels' && !empty($data[$struct['name']])){

				if (!is_array($data[$struct['name']])){
					$children = explode(',', $data[$struct['name']]);
				} else {
					$children = $data[$struct['name']];
				}
				$new_children = [];

				foreach($children as $child_id){
					$child_data = $this->cms_page_panel_model->get_cms_page_panel($child_id);
					unset($child_data['block_id']);
					unset($child_data['cms_page_panel_id']);
					$new_children[] = $this->cms_page_panel_model->create_cms_page_panel($child_data);
				}

				$data[$struct['name']] = $new_children;

				$all_children = $all_children + $new_children;

			}
		}

		if ($data['cms_page_id'] == 999999 || $data['cms_page_id'] == 0){
			$data['sort'] = $data['sort'] + 1;
			$this->cms_page_panel_model->shift_sort($data['panel_name'], $data['sort'], 1);
		}

		unset($data['block_id']);
		unset($data['cms_page_panel_id']);

		$new_block_id = $this->cms_page_panel_model->create_cms_page_panel($data);

		foreach($all_children as $new_child_id){
			$this->cms_page_panel_model->update_cms_page_panel($new_child_id, ['parent_id' => $new_block_id, ]);
		}

		return $new_block_id;

	}

	function get_fk_data($panel_name, $filter = [], $label_field = 'title'){

		// Prefer list title_field from panel definition when caller uses default "title"
		if ($label_field === 'title' && stristr((string)$panel_name, '/')){
			$this->load->model('cms/cms_panel_model');
			$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
			if (!empty($config['list']['title_field'])){
				$label_field = $config['list']['title_field'];
			}
		}

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => $panel_name, 'cms_page_id' => 0] + $filter);
    	
    	$return = array();
    	
    	foreach($panels as $row){
			$label = '';
			if ($label_field !== '' && isset($row[$label_field]) && (string)$row[$label_field] !== ''){
				$label = (string)$row[$label_field];
			} else if (!empty($row['heading'])){
				$label = (string)$row['heading'];
			} else if (!empty($row['_title'])){
				$label = (string)$row['_title'];
			} else if (!empty($row['title'])){
				$label = (string)$row['title'];
			} else {
				$label = '#'.(int)$row['cms_page_panel_id'];
			}
    		$return[(int)$row['cms_page_panel_id']] = str_replace('"', '&quot;', $label);
    	}
    	
    	return $return;
	
	}

	function extend_fk_repeater($panel_name, $data){
		
		foreach($data as $key => $item){
			if (!empty($item)){
				$item_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('cms_page_panel_id' => $item[$panel_name.'_id'], ));
				if (!empty($item_a[0])){
					$data[$key] = $item_a[0];
				}
			}
		}

		return $data;
		
	}

	function get_max_cms_page_panel_id($panel_name){
	
		$sql = "select max(cms_page_panel_id) as cms_page_panel_id from cms_page_panel where panel_name = ? and (cms_page_id = '999999' or cms_page_id = 0) ";
		$query = $this->db->query($sql, array($panel_name, ));
		$return = $query->row_array();
	
		return $return['cms_page_panel_id'];
	
	}

	function swap_param_value($old_value, $new_value){
		
		$sql = "select distinct cms_page_panel_id from cms_page_panel_param where value = ? ";
		$query = $this->db->query($sql, [$old_value]);
		if ($query->num_rows()){
			$ids = $query->result_array();
		} else {
			$ids = [];
		}
		
		$sql = "update cms_page_panel_param set `value` = ? where value = ? ";
		$query = $this->db->query($sql, [$new_value, $old_value]);
		
		foreach($ids as $row){
			$this->cms_page_panel_model->_update_cached_params($row['cms_page_panel_id']);
		}

	}
	
	/**
	 * Write one scalar param for a language (admin translation UI).
	 * Default language uses main param path; others use language-scoped rows.
	 * Does not rebuild param cache — call rebuild_panel_param_cache() after a batch.
	 */

	function set_translated_param($cms_page_panel_id, $path, $value, $language_id){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$path = (string)$path;
		if ($cms_page_panel_id < 1 || $path === ''){
			return false;
		}

		$this->cms_page_panel_model->_ensure_language_model();
		$language_id = $this->cms_page_panel_model->cms_language_model->normalise_language_id($language_id);
		$default_lang = $this->cms_page_panel_model->cms_language_model->normalise_language_id($this->cms_page_panel_model->default_language);
		$value = is_scalar($value) ? (string)$value : '';
		$value = cms_utf8_string($value);

		if ($language_id === $default_lang){
			$this->cms_page_panel_model->_insert_or_update_param($cms_page_panel_id, $path, $value, 0, $default_lang);
			return true;
		}

		$sql = "select cms_page_panel_param_id from cms_page_panel_param where cms_page_panel_id = ? and name = ? and language = ? limit 1 ";
		$query = $this->db->query($sql, [$cms_page_panel_id, $path, $language_id]);

		if ($query->num_rows()){
			$row = $query->row_array();
			$sql = "update cms_page_panel_param set value = ? , search = 0 where cms_page_panel_param_id = ? ";
			$this->db->query($sql, [$value, $row['cms_page_panel_param_id']]);
		} else {
			$sql = "insert into cms_page_panel_param set cms_page_panel_id = ? , name = ? , value = ? , search = 0 , language = ? ";
			$this->db->query($sql, [$cms_page_panel_id, $path, $value, $language_id]);
		}

		return true;

	}

	function rebuild_panel_param_cache($cms_page_panel_id){

		$this->cms_page_panel_model->_update_cached_params((int)$cms_page_panel_id);

	}

}
