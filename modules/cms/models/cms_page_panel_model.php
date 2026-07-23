<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists(__NAMESPACE__.'\\array_merge_recursive_ex')){

	function array_merge_recursive_ex(array $array1, array $array2){
			
		$merged = $array1;

		foreach ($array2 as $key => & $value) {
			if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
				$merged[$key] = array_merge_recursive_ex($merged[$key], $value);
			} else if (is_numeric($key)) {
				if (!in_array($value, $merged)) {
					$merged[] = $value;
				}
			} else {
				$merged[$key] = $value;
			}
		}

		return $merged;
			
	}

}

if (!function_exists(__NAMESPACE__.'\\recursive_keys')){

	function recursive_keys($array, $prefix = ''){

		$return = [];

		foreach ($array as $k => $v){

			if (is_numeric($k)){
				$k = str_pad($k, 6, '0', STR_PAD_LEFT);
			}

			if (!is_array($v)){
				$return[] = $prefix.$k;
			} else {
				$return = array_merge($return, recursive_keys($v, $prefix.$k.'.'));
			}

		}

		return $return;

	}

}

class cms_page_panel_model extends \Model {
	
	var $default_language;
	var $_panel_table_cache = [];
	
	function __construct(){
		
		$this->_ensure_language_model();
		$this->default_language = $this->cms_language_model->get_default();

	}

	function _ensure_language_model(){

		if (!empty($this->cms_language_model)){
			return;
		}

		$CI =& get_instance();
		if (empty($CI->cms_language_model)){
			$this->load->model('cms/cms_language_model');
		}

		$this->cms_language_model = $CI->cms_language_model;

	}

	function get_panel_table_name($panel_name) {
		if (!stristr($panel_name, '/')) {
			return '';
		}
		list($module, $panel) = explode('/', $panel_name, 2);
		return $module.'_'.$panel;
	}

	function get_panel_table_fields($panel_name) {
		if (isset($this->_panel_table_cache[$panel_name])) {
			return $this->_panel_table_cache[$panel_name];
		}

		// Shortcuts store target cms_page_panel_id as panel_name (no module/panel definition or table)
		if (empty($panel_name) || !stristr($panel_name, '/')) {
			$this->_panel_table_cache[$panel_name] = [];
			return [];
		}

		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		$fields = [];

		if (!empty($config['list']) && !empty($config['item'])) {
			foreach ($config['item'] as $item) {
				if (!empty($item['table']) && $item['table'] == '1' && !empty($item['name'])) {
					$fields[$item['name']] = $item;
				}
			}
		}

		$this->_panel_table_cache[$panel_name] = $fields;
		return $fields;
	}

	function panel_table_exists($panel_name) {
		$table = $this->get_panel_table_name($panel_name);
		if (!$table) {
			return false;
		}
		return $this->db->table_exists($table);
	}

	function _read_panel_table_row($cms_page_panel_id, $panel_name) {
		$fields = $this->get_panel_table_fields($panel_name);
		if (empty($fields) || !$this->panel_table_exists($panel_name)) {
			return [];
		}

		$table = $this->get_panel_table_name($panel_name);
		$sql = "select * from `{$table}` where cms_page_panel_id = ? limit 1 ";
		$query = $this->db->query($sql, [(int)$cms_page_panel_id]);
		if (!$query->num_rows()) {
			return [];
		}

		$row = $query->row_array();
		unset($row['cms_page_panel_id']);
		return $row;
	}

	/**
	 * Normalise a value for a panel-table column (empty fk → 0 for int types).
	 */
	function _normalise_panel_table_value($value, $field_spec = []){

		$table_type = $field_spec['table_type'] ?? '';
		$is_int = (bool)preg_match('/^int(_signed)?(:\d+)?$/i', trim((string)$table_type));

		if ($is_int){
			if ($value === null || $value === '' || $value === false){
				return 0;
			}
			return (int)$value;
		}

		return $value;

	}

	function _write_panel_table_row($cms_page_panel_id, $panel_name, $data) {
		$fields = $this->get_panel_table_fields($panel_name);
		// No table → caller must keep values in params (_split leaves them when table missing)
		if (empty($fields) || !$this->panel_table_exists($panel_name)) {
			return;
		}

		$row = [];
		foreach ($fields as $name => $spec) {
			if (array_key_exists($name, $data)) {
				$row[$name] = $this->_normalise_panel_table_value($data[$name], $spec);
			}
		}
		if (empty($row)) {
			return;
		}

		$table = $this->get_panel_table_name($panel_name);
		$sql = "select cms_page_panel_id from `{$table}` where cms_page_panel_id = ? limit 1 ";
		$query = $this->db->query($sql, [(int)$cms_page_panel_id]);

		if ($query->num_rows()) {
			$sets = [];
			$bind = [];
			foreach ($row as $col => $val) {
				$sets[] = '`'.$col.'` = ?';
				$bind[] = $val;
			}
			$bind[] = (int)$cms_page_panel_id;
			$sql = "update `{$table}` set ".implode(', ', $sets)." where cms_page_panel_id = ? ";
			$this->db->query($sql, $bind);
		} else {
			$row['cms_page_panel_id'] = (int)$cms_page_panel_id;
			$cols = array_keys($row);
			$sql = "insert into `{$table}` (`".implode('`, `', $cols)."`) values (".implode(', ', array_fill(0, count($cols), '?')).") ";
			$this->db->query($sql, array_values($row));
		}

		foreach (array_keys($fields) as $field_name) {
			$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? and name = ? and language = '' ";
			$this->db->query($sql, [(int)$cms_page_panel_id, $field_name]);
		}
	}

	function _delete_panel_table_row($cms_page_panel_id, $panel_name) {
		if (!$this->panel_table_exists($panel_name)) {
			return;
		}
		$table = $this->get_panel_table_name($panel_name);
		$sql = "delete from `{$table}` where cms_page_panel_id = ? ";
		$this->db->query($sql, [(int)$cms_page_panel_id]);
	}

	function _overlay_panel_table_fields(&$panel_params, $cms_page_panel_id, $panel_name) {
		$table_row = $this->_read_panel_table_row($cms_page_panel_id, $panel_name);
		if (!empty($table_row)) {
			$panel_params = array_merge($panel_params, $table_row);
		}
	}

	function _split_panel_table_fields($panel_name, &$data) {
		$table_data = [];
		$fields = $this->get_panel_table_fields($panel_name);
		// If panel table is not created yet (schema not applied), leave fields in params
		// so values still save — otherwise subcategory_id etc. are stripped and lost.
		if (empty($fields) || !$this->panel_table_exists($panel_name)) {
			return [];
		}
		foreach ($fields as $name => $spec) {
			if (array_key_exists($name, $data)) {
				$table_data[$name] = $data[$name];
				unset($data[$name]);
			}
		}
		return $table_data;
	}

	/**
	 * Write field values into the general panel param store (named params + JSON cache).
	 * Used when reverse-migrating panel-table columns back to normal fields.
	 * Does not write panel-table columns (split still applies if field is still table:1).
	 */
	function write_panel_param_fields($cms_page_panel_id, $fields) {

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id <= 0 || !is_array($fields) || empty($fields)) {
			return false;
		}

		$this->update_cms_page_panel($cms_page_panel_id, $fields, false);
		return true;

	}

	/**
	 * Item field names from panel definition (settings excluded) — for demote checks.
	 */
	function get_panel_item_field_names($panel_name) {

		if (empty($panel_name) || !stristr($panel_name, '/')) {
			return [];
		}

		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		$names = [];
		if (!empty($config['item']) && is_array($config['item'])) {
			foreach ($config['item'] as $item) {
				if (!empty($item['name'])) {
					$names[$item['name']] = $item['name'];
				}
			}
		}
		return array_values($names);

	}
	
	/**
	 * get visible normal list members
	 */
	function get_list($panel_name, $filter = array()){

		if (!empty($filter['_sort'])){
			$_sort = $filter['_sort'];
			unset($filter['_sort']);
		}

		$return = [];
		
		$list = $this->get_cms_page_panels_by(array_merge(['panel_name' => $panel_name, 'cms_page_id' => 0, 'show' => '1', 'sort!' => 0, ], $filter));

		foreach($list as $item){
			$return[$item['cms_page_panel_id']] = $item;
		}
		
		if (!empty($_sort)){
			$GLOBALS['_sort'] = $_sort;
			usort($return, array($this, '_list_sort'));
		}
		
		return $return;
		
	}
	
	function get_list_stats($panel_name){
		$sql = "select count(*) as count from `cms_page_panel` where panel_name = ? and (cms_page_id = ? or cms_page_id = ?) and `show` = 1 group by panel_name ";
		$query = $this->db->query($sql, array($panel_name, 999999, 0, ));
		return $query->row_array();
	}

	function get_list_item_title($row){

		$panel_name = !empty($row['panel_name']) ? $row['panel_name'] : '';

		if ($this->_has_panel_heading($panel_name, $row)){

			$ci = &get_instance();
			$title = $ci->run_panel_method($panel_name, 'panel_heading', $row);

			if (!is_array($title)){
				return substr(strip_tags($title), 0, 98);
			}

		}

		if ($this->_definition_has_heading_field($panel_name) && !empty($row['heading'])){
			return substr(strip_tags($row['heading']), 0, 98);
		}

		$cms_page_panel_id = !empty($row['cms_page_panel_id']) ? $row['cms_page_panel_id'] : 0;

		return substr(strip_tags($panel_name.'='.$cms_page_panel_id), 0, 98);

	}

	function _has_panel_heading($panel_name, $row = []){

		if ($panel_name === '' || $panel_name === null){
			return false;
		}

		if (!isset($GLOBALS['_panel_has_heading'])){
			$GLOBALS['_panel_has_heading'] = [];
		}
		if (array_key_exists($panel_name, $GLOBALS['_panel_has_heading'])){
			return $GLOBALS['_panel_has_heading'][$panel_name];
		}

		$ci = &get_instance();

		if (!empty($row['_extends'])){
			$files = $ci->get_panel_filenames($panel_name, $row, $row['_extends']);
		} else {
			$files = $ci->get_panel_filenames($panel_name, $row);
		}

		$has = false;

		if (!empty($files['controller'])
				&& $ci->_controller_has_method($files['controller'], $files['module'], $files['name'], 'panel_heading')){
			$has = true;
		}

		if (!$has && !empty($files['extend_controllers'])){
			foreach ($files['extend_controllers'] as $ext){
				if ($ci->_controller_has_method($ext['controller'], $ext['module'], $ext['name'], 'panel_heading')){
					$has = true;
					break;
				}
			}
		}

		$GLOBALS['_panel_has_heading'][$panel_name] = $has;

		return $has;

	}

	function _definition_has_heading_field($panel_name){

		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);

		if (empty($config['item'])){
			return false;
		}

		foreach ($config['item'] as $struct){
			if (!empty($struct['name']) && $struct['name'] == 'heading'){
				return true;
			}
		}

		return false;

	}
	
	function _list_sort($a, $b){
			
		$al = strtolower($a[$GLOBALS['_sort']]);
		$bl = strtolower($b[$GLOBALS['_sort']]);

		if ($al == $bl) {
			return 0;
		}
		
		return ($al > $bl) ? +1 : -1;
			
	}
	
	function is_list_slug($slug){

		if ($slug === '' || $slug === null){
			return false;
		}

		// Namespaced list template slugs: shop_product → shop/product
		$this->load->model('cms/cms_page_model');
		$panel_from_slug = $this->cms_page_model->list_template_panel_from_slug($slug);
		if ($panel_from_slug !== ''){
			foreach ($this->cms_page_model->get_linkable_list_types() as $type){
				if ($type['panel_name'] === $panel_from_slug){
					return true;
				}
			}
		}

		// Bare panel basename definitions (definitions/{slug}.json with list)
		foreach ($GLOBALS['config']['modules'] as $module){

			$filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/definitions/'.$slug.'.json';

			if (!file_exists($filename)){
				continue;
			}

			$config = cms_json_decode(file_get_contents($filename), $filename);

			if (!empty($config['list'])){
				return true;
			}

		}

		return false;

	}

	function get_lists(){
	
		$this->load->model('cms/cms_panel_model');
	
		$return = [];
	
		foreach ($GLOBALS['config']['modules'] as $module){
			foreach(glob($GLOBALS['config']['base_path'].'modules/'.$module.'/definitions/*.json') as $filename){
				$list_name = basename($filename, '.json');
				$block_config = $this->cms_panel_model->get_cms_panel_config($module.'/'.$list_name);
				if (!empty($block_config['list'])){
					$return[$module.'/'.$list_name] = $module.'/'.$list_name;
				}
			}
		}
	
		return $return;
	
	}

	/**
	 * Search weights from panel definition item/settings fields ("search": "1"–"3").
	 * Used when update_cms_page_panel does not receive search_params (API/sync saves).
	 */
	function get_search_params_for_panel_name($panel_name){

		$panel_name = trim((string)$panel_name);
		if ($panel_name === '' || !stristr($panel_name, '/')){
			return [];
		}

		$this->load->model('cms/cms_panel_model');
		$panel_config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		if (!is_array($panel_config)){
			return [];
		}

		// List item structure (sort > 0) merges item fields + extends
		$panel_structure = $this->cms_panel_model->get_cms_panel_edit_structure($panel_config, 0, 0, 1);
		if (!is_array($panel_structure) || empty($panel_structure)){
			$panel_structure = $this->cms_panel_model->get_cms_panel_edit_structure($panel_config, 0, 0, 0);
		}
		if (!is_array($panel_structure)){
			return [];
		}

		$search_params = [];
		foreach ($panel_structure as $struct){
			if (empty($struct['name'])){
				continue;
			}
			if (!empty($struct['search'])){
				$search_params[$struct['name']] = $struct['search'];
			}
			if (($struct['type'] ?? '') === 'repeater' && !empty($struct['fields']) && is_array($struct['fields'])){
				foreach ($struct['fields'] as $r_struct){
					if (!empty($r_struct['name']) && !empty($r_struct['search'])){
						$search_params[$struct['name']][$r_struct['name']] = $r_struct['search'];
					}
				}
			}
		}

		return $search_params;

	}

	/**
	 * Re-apply definition search weights on all params for panels of $panel_name.
	 * Returns number of param rows updated.
	 */
	function reindex_search_weights_for_panel_name($panel_name){

		$search_params = $this->get_search_params_for_panel_name($panel_name);
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
	function get_list_neighbours($panel_name, $cms_page_panel_id, $circular = true){
		
		$return = [
				'last_id' => false,
				'next_id' => false,
		];
		
		// get last and next events
		$items = $this->cms_page_panel_model->get_list($panel_name);

		$found = false;
		foreach($items as $item){
			if ($cms_page_panel_id == $item['cms_page_panel_id']){
				$found = true;
			} else {
				if (!$found){
					$return['last_id'] = $item['cms_page_panel_id'];
				} else if (empty($return['next_id'])) {
					$return['next_id'] = $item['cms_page_panel_id'];
				}
			}
		}
		
		if ($circular){
		
			if (empty($return['last_id'])){
				end($items);
				$current = current($items);
				$return['last_id'] = $current['cms_page_panel_id'];
			}
			 
			if (empty($return['next_id'])){
				reset($items);
				$current = current($items);
				$return['next_id'] = $current['cms_page_panel_id'];
			}
		
		}
		
		$return = array_merge($return);
		
		return $return;
		
	}
	
	
	// detailed data
	function _insert_param($cms_page_panel_id, $name, $value, $search = 0){
		if (is_array($value)){
			foreach($value as $_name => $_value){
				
				if (is_numeric($_name)){
					$_name = str_pad($_name, 3, 0, STR_PAD_LEFT);
				}
				$this->_insert_param($cms_page_panel_id, ($name ? $name.'.' : '').$_name, $_value, $search);
				
			}
		} else {
			$sql = "insert into cms_page_panel_param set cms_page_panel_id = ? , name = ? , value = ? , search = ? ";
			$this->db->query($sql, array($cms_page_panel_id, $name, $value, $search, ));
		}
	}

	function _insert_or_update_param($cms_page_panel_id, $name, $value, $search = 0, $translate = ''){

		$this->_ensure_language_model();

		if (is_array($value)){
			
			if (isset($value['panel_params'])){
				unset($value['panel_params']);
			}

			foreach($value as $_name => $_value){
				
				// for array order preserving, save with extra padded zeroes
				if (is_numeric($_name)){
					$_name = str_pad($_name, 6, '0', STR_PAD_LEFT);
				}

				if (is_array($search) && !empty($search[$_name])){
					// isnt numeric and has appropriate search param key
					$search_param = $search[$_name];
				} else if ((!is_numeric($_name) && (string)(int)$_name !== $_name) && is_array($search) && empty($search[$_name])){
					// isnt numeric and doesnt have key = defaults to zero
					$search_param = 0;
				} else {
					// if numeric, give search param forward unchanged
					$search_param = $search;
				}
				
				if (is_array($translate) && !empty($translate[$_name])){
					// isnt numeric and has appropriate translate param key
					$translate_param = $translate[$_name];
				} else if (is_numeric($_name)) { 
					// if numeric - give over to repeater elements unchanged
					$translate_param = $translate;
				} else if (is_array($translate) && empty($translate[$_name])) {
					$translate_param = '';
				} else {
					// if string, give translate param value
					$translate_param = $translate;
				}

				$this->_insert_or_update_param($cms_page_panel_id, ($name ? $name.'.' : '').$_name, $_value, $search_param, $translate_param);
				
			}
			
		} else {

				
			if (empty($translate)){
				$translate = '';
			}
				
			$sql = "select cms_page_panel_param_id from cms_page_panel_param where cms_page_panel_id = ? and name = ? ".
					"and language = ? limit 1 ";
			$query = $this->db->query($sql, [$cms_page_panel_id, $name, '']);
			
			// if not to translate or default language, update in main
			if ($query->num_rows()){
				
				if (empty($translate) || $this->cms_language_model->normalise_language_id($translate) === $this->cms_language_model->normalise_language_id($this->default_language)){
					$row = $query->row_array();
					$sql = "update cms_page_panel_param set value = ? , search = ? where cms_page_panel_param_id = ? ";
					$this->db->query($sql, [$value, $search, $row['cms_page_panel_param_id']]);
				}
				
			} else {
				
				$sql = "insert into cms_page_panel_param set cms_page_panel_id = ? , name = ? , value = ? , search = ? , language = ? ";
				$this->db->query($sql, [$cms_page_panel_id, $name, $value, $search, '']);
				
			}
			
			// always add to translations too
			if (!empty($translate)){
				$sql = "select cms_page_panel_param_id from cms_page_panel_param where cms_page_panel_id = ? and name = ? and language = ? limit 1 ";
				$query = $this->db->query($sql, [$cms_page_panel_id, $name, $translate]);
				if ($query->num_rows()){
					$row = $query->row_array();
					$sql = "update cms_page_panel_param set value = ? , search = ? where cms_page_panel_param_id = ? ";
					$this->db->query($sql, [$value, $search, $row['cms_page_panel_param_id']]);
				} else {
					$sql = "insert into cms_page_panel_param set cms_page_panel_id = ? , name = ? , value = ? , search = ? , language = ? ";
					$this->db->query($sql, [$cms_page_panel_id, $name, $value, $search, $translate]);
				}
			}

		}
		
	}
	
	function _update_cached_params($cms_page_panel_id){
		
		$this->load->model('cms/cms_panel_model');
		
		// update cached params
		$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? and name = ? ";
		$this->db->query($sql, array($cms_page_panel_id, '',));
		
		$sql = "select * from cms_page_panel_param where cms_page_panel_id = ? ";
		$query = $this->db->query($sql, array($cms_page_panel_id, '',));
		$result = $query->result_array();
		
		$panel_params = [];
		foreach($result as $row){
// _print_r($row);
			if ($row['value'] === '__ARRAY__'){
				$row['value'] = [];
				$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? and name = ? ";
				$this->db->query($sql, [$cms_page_panel_id, $row['name']]);
				continue;
			}
			
			if(!empty($row['language'])){
				$row['name'] = '_translations.'.$row['language'].'.'.$row['name'];
			}

			$keys = explode('.', $row['name']);
			$arr = &$panel_params;
			foreach ($keys as $key) {
				
				// turn numeric keys back to numbers
				
				if (is_numeric($key)){
					$key = (int)$key;
				}
// _print_r($arr);	
				
				if (is_string($arr)){
					$arr = [];
				}

				if (!isset($arr[$key]) || is_string($arr)){
					$arr[$key] = [];
				}
				
				$arr = &$arr[$key];
				
			}
			
			
			$arr = $row['value'];
			
		}
		
		// add empty arrays from panel definition
		$sql = "select * from cms_page_panel where cms_page_panel_id = ? ";
		$query = $this->db->query($sql, [$cms_page_panel_id]);
		
		$page_panel_base = $query->row_array();
		
		if(!empty($page_panel_base['panel_name'])){
			
			$panel_structure = $this->cms_panel_model->get_cms_panel_definition($page_panel_base['panel_name']);
			
			foreach($panel_structure as $struct){
			
				if ($struct['type'] == 'cms_page_panels' && empty($panel_params[$struct['name']])){
					$panel_params[$struct['name']] = [];
				}
			
				if ($struct['type'] == 'repeater' && empty($panel_params[$struct['name']])){
					$panel_params[$struct['name']] = [];
				}
			
			}

			$this->_overlay_panel_table_fields($panel_params, $cms_page_panel_id, $page_panel_base['panel_name']);
			
			$sql = "insert into cms_page_panel_param set cms_page_panel_id = ? , name = '' , value = ? , search = 0 ";
			$this->db->query($sql, [$cms_page_panel_id, json_encode($panel_params, JSON_PRETTY_PRINT)]);
				
		} else {
			
			_html_error('Cant cache non-existant panel id: '.$cms_page_panel_id);
		
		}

	}
	
	function get_cms_page_panel_params($cms_page_panel_id, $language = '', $retry = true){

		$sql = "select value from cms_page_panel_param where cms_page_panel_id = ? and name = ''";
		$query = $this->db->query($sql, [$cms_page_panel_id]);

		if ($query->num_rows()){
    		
	    	$row = $query->row_array();

	    	$return = json_decode($row['value'], true);

	    	if ($language){
    			$this->_ensure_language_model();
    			$translation_branch = $this->cms_language_model->resolve_translation_branch($language, $return['_translations'] ?? []);
    			if (!empty($translation_branch)){
    				$return = array_merge_recursive_ex($return, $translation_branch);
    			}
    		}

			$sql = "select panel_name from cms_page_panel where cms_page_panel_id = ? limit 1 ";
			$pquery = $this->db->query($sql, [$cms_page_panel_id]);
			if ($pquery->num_rows() && is_array($return)) {
				$this->_overlay_panel_table_fields($return, $cms_page_panel_id, $pquery->row_array()['panel_name']);
			}

		} else if($retry){
    		
    		// if empty result, try to update cache
   			$this->_update_cached_params($cms_page_panel_id);
   			return $this->get_cms_page_panel_params($cms_page_panel_id, $language, false);
    		
    	}

    	return $return;
		
	}
	
	function build_visitor_target_badges($targets){

		$badges = [];

		if (empty($targets) || !is_array($targets)){
			return $badges;
		}

		foreach($targets as $heading => $value){
			if ($value !== '' && $value !== null){
				$badges[] = '['.$heading.':'.$value.']';
			}
		}

		return $badges;

	}

	function build_visitor_target_badge_prefix($targets){

		$badges = $this->build_visitor_target_badges($targets);

		if (empty($badges)){
			return '';
		}

		return implode(' ', $badges).' ';

	}

	function _compute_cached_title($base_title, $targets){

		$base_title = substr(strip_tags((string)$base_title), 0, 98);

		return $this->build_visitor_target_badge_prefix($targets).$base_title;

	}

	function _is_list_item_row($row){

		return empty($row['cms_page_id']) && !empty($row['sort']);

	}

	function _is_default_language($language){

		$this->_ensure_language_model();

		if ($language === null || $language === '' || $language === false){
			return true;
		}

		return $this->cms_language_model->normalise_language_id($language) === $this->cms_language_model->normalise_language_id($this->default_language);

	}

	function get_list_item_title_row($cms_page_panel_id, $post_merged = null, $language = null){

		$cms_page_panel_id = (int)$cms_page_panel_id;

		if ($cms_page_panel_id > 0){
			$row = $this->get_cms_page_panel($cms_page_panel_id, $this->default_language, false);
			if (!is_array($row)){
				return false;
			}
			if (is_array($post_merged) && $this->_is_default_language($language)){
				$row = array_merge($row, $post_merged);
				$row['cms_page_panel_id'] = $cms_page_panel_id;
			}
		} else if (is_array($post_merged) && $this->_is_default_language($language)){
			$row = $post_merged;
		} else {
			return false;
		}

		if (!$this->_is_list_item_row($row)){
			return false;
		}

		return $row;

	}

	function _resolve_panel_base_title($cms_page_panel_id, $row, $base_title = null){

		if ($this->_is_list_item_row($row)){
			$title_row = $this->get_list_item_title_row($cms_page_panel_id);
			if (is_array($title_row)){
				return $this->get_list_item_title($title_row);
			}
		}

		if ($base_title !== null && $base_title !== ''){
			return $base_title;
		}

		if (is_numeric($row['panel_name']) && (int)$row['panel_name'] == $row['panel_name']){
			return '';
		}

		if (($row['title'] ?? '') !== ''){
			return $row['title'];
		}

		return $row['title'] ?? '';

	}

	function _refresh_cached_title($cms_page_panel_id, $base_title = null){

		static $refreshing = [];

		$cms_page_panel_id = (int)$cms_page_panel_id;

		if (empty($cms_page_panel_id) || !empty($refreshing[$cms_page_panel_id])){
			return '';
		}

		$refreshing[$cms_page_panel_id] = 1;

		$sql = 'select title, panel_name, cms_page_id, sort from cms_page_panel where cms_page_panel_id = ? limit 1';
		$query = $this->db->query($sql, [$cms_page_panel_id]);

		if (!$query->num_rows()){
			unset($refreshing[$cms_page_panel_id]);
			return '';
		}

		$row = $query->row_array();
		$params = $this->get_cms_page_panel_params($cms_page_panel_id, '');

		if (!is_array($params)){
			$params = [];
		}

		$base_title = $this->_resolve_panel_base_title($cms_page_panel_id, $row, $base_title);

		$cached_title = $this->_compute_cached_title($base_title, $params['_targets'] ?? []);

		if (($params['_title'] ?? '') !== $cached_title){
			$this->_insert_or_update_param($cms_page_panel_id, '_title', $cached_title, 0);
			$this->_update_cached_params($cms_page_panel_id);
		}

		unset($refreshing[$cms_page_panel_id]);

		return $cached_title;

	}

	/**
	 * Keys that do not affect admin list title (skip auto _title refresh when update is only these).
	 */
	function _title_meta_keys(){

		return [
				'show',
				'sort',
				'cms_page_panel_id',
				'cms_page_id',
				'parent_id',
				'panel_name',
				'submenu_anchor',
				'submenu_title',
				'sync_needed',
				'shopify_checked_at',
				'shopify_updated_at',
				'last_update',
				'update_time',
				'update_cms_user_id',
				'create_time',
				'create_cms_user_id',
				'image_update',
				'image_name_hash',
		];

	}

	/**
	 * Parse control flag _update_title from data array (not stored).
	 * Returns [cleaned $data, null|true|false] — null = auto.
	 */
	function _extract_update_title_flag($data){

		if (!is_array($data) || !array_key_exists('_update_title', $data)){
			return [$data, null];
		}

		$raw = $data['_update_title'];
		unset($data['_update_title']);

		// Explicit skip
		if ($raw === false || $raw === 0 || $raw === '0' || $raw === ''){
			return [$data, false];
		}

		// Explicit force
		return [$data, true];

	}

	/**
	 * Whether to recompute _title after update_cms_page_panel.
	 *
	 * @param bool $purge full panel replace
	 * @param bool|null $flag true force, false skip, null auto
	 * @param array $row_keys cms_page_panel columns written
	 * @param array $param_keys param/table fields written
	 */
	function _should_refresh_panel_title($purge, $flag, $row_keys, $param_keys){

		if ($flag === false){
			return false;
		}

		if ($flag === true){
			return true;
		}

		// Auto
		if (!empty($purge)){
			return true;
		}

		$row_keys = is_array($row_keys) ? $row_keys : [];
		$param_keys = is_array($param_keys) ? $param_keys : [];

		if (in_array('title', $row_keys, true)){
			return true;
		}

		if (in_array('heading', $param_keys, true)){
			return true;
		}

		// Target badges affect cached title prefix
		if (in_array('_targets', $param_keys, true)){
			return true;
		}

		$meta = array_flip($this->_title_meta_keys());

		foreach (array_merge($row_keys, $param_keys) as $key){
			if ($key === '' || $key === '_title'){
				continue;
			}
			// Other underscore system keys (except _targets above): ignore for auto
			if (is_string($key) && $key !== '' && $key[0] === '_'){
				continue;
			}
			if (isset($meta[$key])){
				continue;
			}
			// Non-meta content field present
			return true;
		}

		return false;

	}

	function get_panel_admin_title($row, $lazy_refresh = true){

		if (!empty($row['_title'])){
			return $row['_title'];
		}

		if ($lazy_refresh && !empty($row['cms_page_panel_id'])){
			$base_title = $this->_is_list_item_row($row) ? null : ($row['title'] ?? null);
			$cached_title = $this->_refresh_cached_title($row['cms_page_panel_id'], $base_title);
			if ($cached_title !== ''){
				return $cached_title;
			}
		}

		if (!$this->_is_list_item_row($row) && !empty($row['title'])){
			return $row['title'];
		}

		if (!empty($row['cms_page_panel_id']) && $this->_is_list_item_row($row)){
			$title_row = $this->get_list_item_title_row($row['cms_page_panel_id']);
			if (is_array($title_row)){
				return $this->get_list_item_title($title_row);
			}
		}

		return '';

	}

	function refresh_all_cached_titles(){

		$query = $this->db->query('select cms_page_panel_id from cms_page_panel order by cms_page_panel_id');
		$count = 0;

		foreach($query->result_array() as $row){
			$this->_refresh_cached_title($row['cms_page_panel_id']);
			$count++;
		}

		return $count;

	}

	function panel_matches_visitor_targets($panel){

		if (empty($GLOBALS['config']['targets_enabled']) || empty($_SESSION['targets'])){
			return true;
		}

		if (empty($panel['_targets']) || !is_array($panel['_targets'])){
			return true;
		}

		foreach($panel['_targets'] as $heading => $required_label){

			if ($required_label === '' || $required_label === null){
				continue;
			}

			if (empty($_SESSION['targets'][$heading])){
				return false;
			}

			if ($_SESSION['targets'][$heading] !== $required_label){
				return false;
			}

		}

		return true;

	}

	function get_cms_page_panel($cms_page_panel_id, $language = false, $settings = true){
		
		// Admin UI → CMS language; public site → visitor language (never mix)
		if ($language === false){
			$language = $this->get_content_language();
		}

		$sql = "select * from cms_page_panel where cms_page_panel_id = ? ";
		$query = $this->db->query($sql, array($cms_page_panel_id));
		$row = $query->row_array();
		 
		if (empty($row['cms_page_panel_id'])) {
			return false;
		}

		$panel_params = $this->get_cms_page_panel_params($row['cms_page_panel_id'], $language);
	    
		if (is_array($panel_params)){
			$return = array_merge($panel_params, $row);
		} else {
			$return = $row;
		}

		// add settings if present
		if ($settings){
			$return = array_merge($this->get_cms_page_panel_settings($return['panel_name'], $language), $return);
		}
		
		return $return;
	
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

		$settings_rows = $this->get_cms_page_panels_by([
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
			$existing = $this->get_cms_page_panel_params($cms_page_panel_id);
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

		$this->update_cms_page_panel($cms_page_panel_id, ['panel_params' => $params], true);

		return true;

	}

	function update_cms_page_panel($cms_page_panel_id, $data, $purge = false){

		// Control flag: not stored — true force title, false skip, null auto (see _should_refresh_panel_title)
		list($data, $update_title_flag) = $this->_extract_update_title_flag($data);
		// Requested purge (before settings-panel demotion) counts as full save for title auto
		$requested_purge = !empty($purge);

		$search_params_explicit = array_key_exists('search_params', $data);
		if ($search_params_explicit){
			$search_params = $data['search_params'];
			unset($data['search_params']);
		} else {
			$search_params = 0;
		}
		
		if (isset($data['translate_params'])){
			$translate_params = $data['translate_params'];
			unset($data['translate_params']);
		} else {
			$translate_params = 0;
		}
		
		$params = !empty($data['panel_params']) ? $data['panel_params'] : array();

		if (isset($data['panel_params'])){
			unset($data['panel_params']);
		}
		
		if (!empty($data['title'])){
			$data['title'] = substr($data['title'], 0, 98);
		}
		
		// check if update time and user needs update
		$keys = array_keys($data);
		$keys = array_diff($keys, ['show', 'sort', 'cms_page_panel_id', 'cms_page_id', 'parent_id', ]);
		foreach($keys as $ckey => $cval){
			if (substr($cval, 0, 1) == '_'){
				unset($keys[$ckey]);
			}
		}
		if (count($keys)){
			$data['update_cms_user_id'] = !empty($_SESSION['cms_user']['cms_user_id']) ? $_SESSION['cms_user']['cms_user_id'] : 0;
			$date = new \DateTime();
			$data['update_time'] = $date->getTimestamp();
		}
		
		// new params stuff
		foreach($data as $key => $value){
			if (!in_array($key, ['cms_page_panel_id', 'cms_page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title'])){
				$params[$key] = $value;
				unset($data[$key]);
			}
		}

		$panel_name = $data['panel_name'] ?? '';
		if (!$panel_name) {
			$sql = "select panel_name from cms_page_panel where cms_page_panel_id = ? limit 1 ";
			$pquery = $this->db->query($sql, [(int)$cms_page_panel_id]);
			if ($pquery->num_rows()) {
				$panel_name = $pquery->row_array()['panel_name'];
			}
		}

		// Apply definition search weights when caller did not pass search_params
		// (e.g. Shopify sync) — otherwise every update zeros cms_page_panel_param.search
		if (!$search_params_explicit && !empty($params) && $panel_name !== ''){
			$search_params = $this->get_search_params_for_panel_name($panel_name);
		}

		$table_data = [];
		if ($panel_name) {
			$table_data = $this->_split_panel_table_fields($panel_name, $params);
		}

		// Keys written this call (for auto title refresh) — before purge may strip others
		$row_keys_written = array_keys($data);
		$param_keys_written = array_merge(array_keys($params), array_keys($table_data));
		$title_for_refresh = array_key_exists('title', $data) ? $data['title'] : null;

		// params data
		if (!empty($params)){
			
			if ($purge){

				$this->load->model('cms/cms_panel_model');
				$panel_config = $this->cms_panel_model->get_cms_panel_config($panel_name);
				if (!empty($panel_config['settings'])) {
					$has_setting_field = false;
					foreach ($panel_config['settings'] as $field) {
						if (!empty($field['name']) && array_key_exists($field['name'], $params)) {
							$has_setting_field = true;
							break;
						}
					}
					if (!$has_setting_field) {
						$purge = false;
					}
				}

				$existing_params = $this->get_cms_page_panel_params($cms_page_panel_id);
				if (is_array($existing_params)){
					foreach($existing_params as $preserve_key => $preserve_value){
						if ($preserve_key !== '' && $preserve_key[0] === '_' && !array_key_exists($preserve_key, $params)){
							$params[$preserve_key] = $preserve_value;
						}
					}
				}

				// build keys (cms\recursive_keys — file-scope helper)
				$recursive_keys = recursive_keys($params);
				$recursive_keys = array_unique(array_merge($recursive_keys, ['', 'create_cms_user_id', 'create_time', 'update_cms_user_id', 'update_time']));

// 				_print _r($recursive_keys);

				$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? and name not in ('".implode("','", $recursive_keys)."') ";
				$this->db->query($sql, [(int)$cms_page_panel_id]);

			}
			
			$this->_insert_or_update_param($cms_page_panel_id, '', $params, $search_params, $translate_params);
			$this->_update_cached_params($cms_page_panel_id);
		
		}

		if (!empty($table_data) && $panel_name) {
			$this->_write_panel_table_row($cms_page_panel_id, $panel_name, $table_data);
			$this->_update_cached_params($cms_page_panel_id);
		}

		// panel data
		if(!empty($data)){
			$sql = "update cms_page_panel set `".implode('` = ? , `', array_keys($data))."` = ? where cms_page_panel_id = '".(int)$cms_page_panel_id."' ";
			$this->db->query($sql, $data);
		}

		// Full title process (panel_heading / heading / badges) — gated to avoid every meta stamp
		if ($this->_should_refresh_panel_title($requested_purge, $update_title_flag, $row_keys_written, $param_keys_written)){
			$this->_refresh_cached_title($cms_page_panel_id, $title_for_refresh);
		}

		$this->invalidate_html_cache($cms_page_panel_id);
		$this->_invalidate_page_cache($cms_page_panel_id);
		
	}

	function _invalidate_page_cache($cms_page_panel_id) {

		$block = $this->get_cms_page_panel($cms_page_panel_id);
		if (empty($block)) {
			return;
		}

		$this->load->model('cms/cms_page_cache_model');

		if (!empty($block['cms_page_id'])) {
			$this->cms_page_cache_model->invalidate_page($block['cms_page_id']);
		} elseif (!empty($block['panel_name'])) {
			$this->cms_page_cache_model->invalidate_list_item($block['panel_name'], $cms_page_panel_id);
		}

	}
	
	function invalidate_html_cache($cms_page_panel_id){
		
		$filestart = $GLOBALS['config']['base_path'].'cache/_'.(int)$cms_page_panel_id.'_';
		array_map('unlink', glob($filestart.'*'));
		
		// if list item, invalidate all related panel caches as well
		$block = $this->get_cms_page_panel($cms_page_panel_id);
		if (empty($block['cms_page_id'])){
			
			$lists = $this->get_lists();
			
			if (in_array($block['panel_name'], $lists)){

				$sql = "select cms_page_panel_id from cms_page_panel_param where value like '%" . $block['panel_name'] . "%' and name = '_cache_lists'";
				
    			$query = $this->db->query($sql);
	    		if ($query->num_rows()){

	    			$ids = [];
	    			
	    			$result = $query->result_array();
    				foreach ($result as $row){
	    				$ids[$row['cms_page_panel_id']] = $row['cms_page_panel_id'];
    				}
    				
					foreach($ids as $id){
    					$filestart = $GLOBALS['config']['base_path'].'cache/_'.(int)$id.'_';
    					array_map('unlink', glob($filestart.'*'));
    				}
    				
	    		}
				
			}
		}
	
	}
	
	/**
	 * insert new page panel into db
	 */
	function create_cms_page_panel($data){

		// Optional skip: _update_title => 0 after create (rare bulk load that sets title later)
		list($data, $update_title_flag) = $this->_extract_update_title_flag($data);
		
		if (!empty($data['panel_params']) && is_array($data['panel_params'])){
			$data = array_merge($data['panel_params'], $data);
		}
		
		if (empty($data['cms_page_id'])){
			$data['cms_page_id'] = isset($data['page_id']) ? $data['page_id'] : 0;
		}
		
		$search_params = array();
		if (!empty($data['search_params'])){
			$search_params = $data['search_params'];
			unset($data['search_params']);
		}
		
		if (empty($data['parent_id'])){
			$data['parent_id'] = 0;
		}
		
		if (empty($data['show'])){
			$data['show'] = 0;
		}
		
		if (empty($data['submenu_anchor'])){
			$data['submenu_anchor'] = '';
		}
		
			if (empty($data['submenu_title'])){
			$data['submenu_title'] = '';
		}
		
		if (empty($data['title'])){
			$data['title'] = '';
		}
		$data['title'] = substr($data['title'], 0, 98);
		
		if (!isset($data['sort'])) $data['sort'] = 1;

		$panel_params = array();
		
		foreach($data as $key => $value){
			if (!in_array($key, array('cms_page_panel_id', 'cms_page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title', ))){
				$panel_params[$key] = $value;
				unset($data[$key]);
			}
		}

		$table_data = [];
		if (!empty($data['panel_name'])) {
			$table_data = $this->_split_panel_table_fields($data['panel_name'], $panel_params);
		}
		
		// check sort
		if (!empty($data['sort']) && $data['sort'] == 'first'){
			$this->shift_sort($data['panel_name'], 0, 1);
			$data['sort'] = 1;
		} elseif (!isset($data['sort']) && ($data['cms_page_id'] == '999999' || $data['cms_page_id'] == 0)) {
			$sort_stats = $this->get_sort_stats($data['panel_name']);
			$data['sort'] = !empty($sort_stats['max_sort']) ? $sort_stats['max_sort'] + 1 : 0;
		} elseif (!empty($data['sort']) && $data['sort'] == 'last'){
			$sort_stats = $this->get_page_panel_sort_stats($data['cms_page_id']);
			$data['sort'] = $sort_stats['max_sort'] + 1;
		}// else stays the same
		
		$sql = "insert into cms_page_panel set `".implode('` = ? , `', array_keys($data))."` = ? ";
		$this->db->query($sql, $data);
		
		$insert_id = $this->db->insert_id();
		
		// creation data
		$panel_params['create_cms_user_id'] = !empty($_SESSION['cms_user']['cms_user_id']) ? $_SESSION['cms_user']['cms_user_id'] : 0;
		$date = new \DateTime();
		$panel_params['create_time'] = $date->getTimestamp();
		$panel_params['update_cms_user_id'] = $panel_params['create_cms_user_id'];
		$panel_params['update_time'] = $panel_params['create_time'];
		
		if (!empty($panel_params)){
		
			// detailed data
			$this->_insert_or_update_param($insert_id, '', $panel_params, $search_params);
			$this->_update_cached_params($insert_id);
		}

		if (!empty($table_data) && !empty($data['panel_name'])) {
			$this->_write_panel_table_row($insert_id, $data['panel_name'], $table_data);
			$this->_update_cached_params($insert_id);
		}

		// Always full title process after create (params exist) unless explicitly skipped
		if ($update_title_flag !== false){
			$this->_refresh_cached_title($insert_id, $data['title'] ?? null);
		}
		
		$this->invalidate_html_cache($insert_id);
		$this->_invalidate_page_cache($insert_id);
		
		return $insert_id;
		
	}
		
	/**
	 *
	 * @param array $orders
	 * 
	 * save page panels order by array: block_id => sort
	 * 
	 */
	function save_orders($orders){
		
		foreach($orders as $name => $value){
    		$sql = "update cms_page_panel set sort = ? where cms_page_panel_id = ? ";
	    	$this->db->query($sql, array($value, $name, ));
		}
    	
	}
	
	function delete_cms_page_panel($cms_page_panel_id){
		
		if (empty($cms_page_panel_id)){
			error_log('Deleting empty block '.serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
			return;
		}
		
		$this->invalidate_html_cache($cms_page_panel_id);
		$this->_invalidate_page_cache($cms_page_panel_id);
		
		// delete children
		$sql = "select cms_page_panel_id from cms_page_panel where parent_id = ? ";
   		$query = $this->db->query($sql, array($cms_page_panel_id));
    	$result = $query->result_array();
    	foreach($result as $child){
    		$this->delete_cms_page_panel($child['cms_page_panel_id']);
    	}
		
		// update parent
	    $cms_page_panel = $this->get_cms_page_panel($cms_page_panel_id);
	    
	    if ($cms_page_panel){
	    	if (!empty($cms_page_panel['parent_id'])){
	    		$parent = $this->get_cms_page_panel($cms_page_panel['parent_id']);
	    		if (!empty($parent['panel_name'])){
	    			$this->load->model('cms/cms_panel_model');
					$parent_config = $this->cms_panel_model->get_cms_panel_config($parent['panel_name']);
					foreach($parent_config['item'] as $item){
						if ($item['type'] == 'cms_page_panels'){
							
							if (!is_array($parent[$item['name']])){
								$children = explode(',', $parent[$item['name']]);
							} else {
								$children = $parent[$item['name']];
							}
							
							if(($key = array_search($cms_page_panel_id, $children)) !== false) {
    							unset($children[$key]);
    							$this->update_cms_page_panel($cms_page_panel['parent_id'], array($item['name'] => $children, ));
							}
						}
					}
	    		}
	    	}
	    }
		
		if (!empty($cms_page_panel['panel_name'])) {
			$this->_delete_panel_table_row($cms_page_panel_id, $cms_page_panel['panel_name']);
		}

		$sql = "delete from cms_page_panel where cms_page_panel_id = ? ";
	    $this->db->query($sql, array($cms_page_panel_id, ));
		
		$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? ";
	    $this->db->query($sql, array($cms_page_panel_id, ));
	    
	    // shortcuts
	    $sql = "delete from cms_page_panel where panel_name = ? ";
	    $this->db->query($sql, array($cms_page_panel_id, ));
	    
	    // delete slug pointing to this panel
	    $this->load->model('cms/cms_slug_model');
	    $this->cms_slug_model->delete_slug($cms_page_panel['panel_name'].'='.$cms_page_panel_id);
	    	
	}
	
	function _cms_page_panels_list_query_context(&$filter){

		$context = [
			'limit' => null,
			'offset' => null,
			'order' => 'asc',
			'sql_filter' => [],
			'table_filter' => [],
			'params_filter' => [],
			'sql_arrays' => [],
			'table_arrays' => [],
			'sql_filter_str' => '',
			'table_filter_str' => '',
			'sql_arrays_str' => '',
			'table_arrays_str' => '',
			'where_str' => '',
			'table_join' => '',
			'table_select' => '',
			'bind' => [],
			'use_panel_table' => false,
			'panel_table' => '',
			'table_fields' => [],
		];

		if (isset($filter['_limit'])){
			$context['limit'] = (int)$filter['_limit'];
			unset($filter['_limit']);
		}

		if (isset($filter['_start'])){
			$context['offset'] = (int)$filter['_start'];
			unset($filter['_start']);
		}

		if (isset($filter['_order'])){
			$context['order'] = $filter['_order'];
			unset($filter['_order']);
		}

		if (!is_array($filter)){
			error_log('Bad filter in cms_page_panel_model list query!');
			$filter = [];
		}

		$panel_name = $filter['panel_name'] ?? '';
		if (is_array($panel_name)){
			$panel_name = $panel_name[0] ?? '';
		}

		if ($panel_name){
			$context['table_fields'] = $this->get_panel_table_fields($panel_name);
			if (!empty($context['table_fields']) && $this->panel_table_exists($panel_name)){
				$context['use_panel_table'] = true;
				$context['panel_table'] = $this->get_panel_table_name($panel_name);
				$table_cols = [];
				foreach (array_keys($context['table_fields']) as $col){
					$table_cols[] = 't.`'.$col.'`';
				}
				$context['table_select'] = ', '.implode(', ', $table_cols);
				$context['table_join'] = ' join `'.$context['panel_table'].'` t on t.cms_page_panel_id = a.cms_page_panel_id ';
			}
		}

		foreach($filter as $key => $value){
			$tkey = str_replace('!', '', $key);
			if (in_array($tkey, ['cms_page_panel', 'cms_page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title', ])){

				if (!is_array($value)){
					$context['sql_filter'][$key] = $value;
				} else if (!empty($value) && is_array($value)){

					$new_array = [];
					foreach($value as $el){
						$new_array[] = str_replace(['\'', '"'], ['\\\'', '\\\"'], $el);
					}

					$context['sql_arrays'][] = ' a.`'.$tkey.'` '.($tkey != $key ? ' not ' : '')." in ('".implode("','", $new_array)."') ";
				}

			} else if ($context['use_panel_table'] && isset($context['table_fields'][$tkey])) {
				if (!is_array($value)){
					$context['table_filter'][$key] = $value;
				} else if (!empty($value) && is_array($value)){

					$new_array = [];
					foreach($value as $el){
						$new_array[] = str_replace(['\'', '"'], ['\\\'', '\\\"'], $el);
					}

					$context['table_arrays'][] = ' t.`'.$tkey.'` '.($tkey != $key ? ' not ' : '')." in ('".implode("','", $new_array)."') ";
				}
			} else {
				$context['params_filter'][$key] = $value;
			}
		}

		$sql_filter_parts = [];
		foreach ($context['sql_filter'] as $key => $value){
			$tkey = str_replace('!', '', $key);
			$sql_filter_parts[] = 'a.`'.$tkey.'` '.($tkey != $key ? '!=' : '=').' ?';
		}
		if (!empty($sql_filter_parts)){
			$context['sql_filter_str'] = implode(' and ', $sql_filter_parts);
		}

		$table_filter_parts = [];
		foreach ($context['table_filter'] as $key => $value){
			$tkey = str_replace('!', '', $key);
			$table_filter_parts[] = 't.`'.$tkey.'` '.($tkey != $key ? '!=' : '=').' ?';
		}
		if (!empty($table_filter_parts)){
			$context['table_filter_str'] = implode(' and ', $table_filter_parts);
		}

		$context['sql_arrays_str'] = !empty($context['sql_arrays']) ? implode(' and ', $context['sql_arrays']) : '';
		$context['table_arrays_str'] = !empty($context['table_arrays']) ? implode(' and ', $context['table_arrays']) : '';

		$where_parts = array_filter([$context['sql_filter_str'], $context['sql_arrays_str'], $context['table_filter_str'], $context['table_arrays_str']]);
		$context['where_str'] = implode(' and ', $where_parts);
		$context['bind'] = array_merge(array_values($context['sql_filter']), array_values($context['table_filter']));

		return $context;

	}

	function count_cms_page_panels_list_by($filter){

		$count_filter = $filter;
		$ctx = $this->_cms_page_panels_list_query_context($count_filter);

		if (!empty($ctx['params_filter'])){
			return count($this->get_cms_page_panels_by($filter));
		}

		$sql = 'select count(*) as total from `cms_page_panel` a '.$ctx['table_join'].
				($ctx['where_str'] ? ' where '.$ctx['where_str'].' ' : ' ');

		$query = $this->db->query($sql, $ctx['bind']);
		$return = $query->row_array();

		return (int)$return['total'];

	}

	function get_cms_page_panels_list_by($filter){

		$list_filter = $filter;
		$ctx = $this->_cms_page_panels_list_query_context($list_filter);

		if (!empty($ctx['params_filter'])){
			return $this->get_cms_page_panels_by($filter);
		}

		$sql = 'select a.cms_page_panel_id, a.cms_page_id, a.parent_id, a.show, a.sort, a.title, a.panel_name, a.submenu_anchor, a.submenu_title, '.
				'JSON_UNQUOTE(JSON_EXTRACT(p.value, \'$._title\')) as _title'.$ctx['table_select'].
				' from `cms_page_panel` a '.
				'left join cms_page_panel_param p on p.cms_page_panel_id = a.cms_page_panel_id and p.name = \'\' '.
				$ctx['table_join'].
				($ctx['where_str'] ? ' where '.$ctx['where_str'].' ' : ' ').
				'order by a.sort '.$ctx['order'];

		$bind = $ctx['bind'];

		if ($ctx['limit'] > 0){
			$sql .= ' limit ? offset ?';
			$bind[] = $ctx['limit'];
			$bind[] = $ctx['offset'] ?? 0;
		}

		$query = $this->db->query($sql, $bind);

		if (!$query){
			_html_error('Missing field or table: cms_page_panel list query');
			die();
		}

		if ($query->num_rows()){
			return $query->result_array();
		}

		return [];

	}

	function count_cms_page_panels_by($filter){
		
		$fields = array_keys($filter);
		
		foreach($fields as $key => $field){
			if (in_array($field, array('block_id', 'page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title', ))) {
				unset($fields[$key]);
			}
		}

		if (count($fields) == 0){
			$fields = array_keys($filter);
			$sql = "select count(*) as total from `cms_page_panel` where `" . preg_replace("/[^A-Za-z0-9_=?` ]/", '', implode('` = ? and `', $fields)) . "` = ? ";
			
	    	$query = $this->db->query($sql, $filter);
		    $return = $query->row_array();
	
	    	return $return['total'];
		} else {
			$return = $this->get_cms_page_panels_by($filter);
			return count($return);
		}
    	
	}
	
	/*
	 * get panels according the filter
	 * 
	 * fieldname => value
	 * fieldname => array(value, value, value ...)
	 * 
	 * fieldname! => ...  means not value or values
	 * 
	 */
	function get_cms_page_panels_by($filter){
		
		if (isset($filter['_limit'])){
			$limit = (int)$filter['_limit'];
			unset($filter['_limit']);
		}

		if (isset($filter['_start'])){
			$offset = (int)$filter['_start'];
			unset($filter['_start']);
		}
		
		if (isset($filter['_order'])){
			$order = $filter['_order'];
			unset($filter['_order']);
		} else {
			$order = 'asc';
		}

		$list_language = false;
		if (!empty($filter['_default_language'])){
			$list_language = '';
			unset($filter['_default_language']);
		}

		$fields_only = false;
		$select_fields = [];

		if (isset($filter['_fields'])){
			$select_fields = $filter['_fields'];
			unset($filter['_fields']);
			$base_table_fields = ['cms_page_panel_id', 'cms_page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title'];
			$fields_only = !array_diff($select_fields, $base_table_fields);
		}
				
		// separate filters
		$sql_filter = array();
		$table_filter = array();
		$params_filter = array();
		$sql_arrays = array();
		$table_arrays = array();
		$sql_filter_str = '';
		$table_filter_str = '';
		
		if (!is_array($filter)){
			error_log('Bad filter in cms_page_panel_model!');
			$filter = [];
		}

		$panel_name = $filter['panel_name'] ?? '';
		$table_fields = [];
		$use_panel_table = false;
		$panel_table = '';

		if ($panel_name) {
			$table_fields = $this->get_panel_table_fields($panel_name);
			if (!empty($table_fields) && $this->panel_table_exists($panel_name)) {
				$use_panel_table = true;
				$panel_table = $this->get_panel_table_name($panel_name);
			}
		}

		foreach($filter as $key => $value){
			$tkey = str_replace('!', '', $key);
			if (in_array($tkey, array('cms_page_panel', 'cms_page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title', ))){
				
				// check for arrays in sql filter
				if (!is_array($value)){
					$sql_filter[$key] = $value;
				} else if (!empty($value) && is_array($value)){
					
					// sanitise the array
					$new_array = array();
					foreach($value as $el){
						$new_array[] = str_replace(array("'", '"'), array("\\\'", '\\\"'), $el);
					}
					
					$sql_arrays[] = " a.`".$tkey."` ".($tkey != $key ? ' not ' : '')." in ('".implode("','", $new_array)."') ";
				}
			
			} else if ($use_panel_table && isset($table_fields[$tkey])) {
				if (!is_array($value)){
					$table_filter[$key] = $value;
				} else if (!empty($value) && is_array($value)){
					$new_array = array();
					foreach($value as $el){
						$new_array[] = str_replace(array("'", '"'), array("\\\'", '\\\"'), $el);
					}
					$table_arrays[] = " t.`".$tkey."` ".($tkey != $key ? ' not ' : '')." in ('".implode("','", $new_array)."') ";
				}
			} else {
				$params_filter[$key] = $value;
			}
		}

		$sql_filter_parts = [];
		foreach ($sql_filter as $key => $value) {
			$tkey = str_replace('!', '', $key);
			$sql_filter_parts[] = 'a.`'.$tkey.'` '.($tkey != $key ? '!=' : '=').' ?';
		}
		if (!empty($sql_filter_parts)) {
			$sql_filter_str = implode(' and ', $sql_filter_parts);
		}

		$table_filter_parts = [];
		foreach ($table_filter as $key => $value) {
			$tkey = str_replace('!', '', $key);
			$table_filter_parts[] = 't.`'.$tkey.'` '.($tkey != $key ? '!=' : '=').' ?';
		}
		if (!empty($table_filter_parts)) {
			$table_filter_str = implode(' and ', $table_filter_parts);
		}
		
		if(!empty($sql_arrays)){
			$sql_arrays_str = implode(' and ', $sql_arrays);
		} else {
			$sql_arrays_str = '';
		}

		if(!empty($table_arrays)){
			$table_arrays_str = implode(' and ', $table_arrays);
		} else {
			$table_arrays_str = '';
		}

		$where_parts = array_filter([$sql_filter_str, $sql_arrays_str, $table_filter_str, $table_arrays_str]);
		$where_str = implode(' and ', $where_parts);

		// LEFT JOIN: panel table rows are optional until a table field is first saved.
		// INNER JOIN hid panels with no table row (e.g. shop/product without subcategory_id)
		// so Shopify sync kept treating them as "new" and recreating duplicates.
		$table_join = $use_panel_table ? " left join `{$panel_table}` t on t.cms_page_panel_id = a.cms_page_panel_id " : '';

		if ($fields_only){

			$select_parts = [];
			foreach ($select_fields as $field){
				$select_parts[] = 'a.`'.$field.'`';
			}

			$sql = "select ".implode(', ', $select_parts).", JSON_UNQUOTE(JSON_EXTRACT(p.value, '$._title')) as _title ".
					"from `cms_page_panel` a ".
					"left join cms_page_panel_param p on p.cms_page_panel_id = a.cms_page_panel_id and p.name = '' ".
					$table_join.
					($where_str ? " where ".$where_str." " : " ").
					"order by a.sort ".$order;

		} else {

			$sql = "select a.*, b.value as _params from `cms_page_panel` a ".$table_join.
					"left join cms_page_panel_param b on b.name = '' and b.cms_page_panel_id = a.cms_page_panel_id ".
					($where_str ? " where ".$where_str." " : " ").
					"order by a.sort ".$order;

		}

		$bind = array_merge(array_values($sql_filter), array_values($table_filter));
		$query = $this->db->query($sql, $bind);
		
		if (!$query){
			_html_error('Missing field or table: cms_page_panel or cms_page_panel_param');
			die();
		}
		
    	if ($query->num_rows()){
	    	$return = $query->result_array();
    	} else {
    		$return = array();
    	}
    	
    	if (!$fields_only){

	    	// replace with translated versions
	    	foreach($return as $key => $page_panel){
	    		$language = ($list_language === false) ? $this->get_content_language() : $list_language;
	    		$return[$key] = $this->get_cms_page_panel($page_panel['cms_page_panel_id'], $language, false);
	    	}
	    	
	    	// unpack params - not needed
	    	foreach ($return as $key => $row){
	    		if(!empty($row['_params'])){
	    			$_params = json_decode($row['_params'], true);
	    			if (is_array($_params)){
		    			$return[$key] = array_merge($_params, $row);
	    			}
	    		}
	    		unset($return[$key]['_params']);
	    	}

    	}

    	foreach ($return as $key => $row){
			foreach($params_filter as $field_name => $field_value){
				
				if (stristr($field_name, '!')){
					$not = true;
					$field_name = str_replace('!', '', $field_name);
				} else {
					$not = false;
				}

				if (!is_array($field_value)){
					$field_value = array($field_value);
				}
				
				if (!isset($row[$field_name])){
					$row_value = '';
				} else {
					$row_value = $row[$field_name];
				}
				
				if ( !$not && !in_array($row_value, $field_value) ){
					$return[$key]['_to_delete'] = 1;
				} elseif ( $not && in_array($row_value, $field_value) ){
					$return[$key]['_to_delete'] = 1;
				}
				
			}
    	}

    	foreach ($return as $key => $row){
    		if (!empty($row['_to_delete'])){
    			unset($return[$key]);
    		}
    	}
    	
    	if (isset($limit) || isset($offset)){
	    	// return first limit rows
			$return = array_slice($return, (empty($offset) ? 0 : $offset), (empty($limit) ? 1 : $limit));
    	} else {
    		$return = array_values($return);
    	}

    	foreach($return as $key => $cms_page_panel){
    		if (!isset($cms_page_panel['cms_page_id'])){
    			$return[$key]['cms_page_id'] = $cms_page_panel['page_id'];
    		}
    	}
    	
    	// check for page panel settings
    	if (!$fields_only){
	    	foreach($return as $key => $cms_page_panel){
	    		if ($cms_page_panel['cms_page_id']){
	    			$return[$key] = array_merge($this->get_cms_page_panel_settings($cms_page_panel['panel_name']), $cms_page_panel);
	   			}
	    	}
    	}

		return $return;
    
	}
	
	/**
	 * 
	 * @param unknown $cms_panel_name - module/panel_name
	 * @param string $language - language id for translated settings (empty = content language for this request)
	 */
	function get_cms_page_panel_settings($cms_panel_name, $language = ''){

		if ($language === ''){
			$language = $this->get_content_language();
		}
		
		if (is_numeric($cms_panel_name)){
			$panel_data = $this->get_cms_page_panel($cms_panel_name, $language, false);
			$cms_panel_name = $panel_data['panel_name'];
		}
		
		if (!stristr($cms_panel_name, '/')){
			_html_error('Can\'t load cms panel settings, module not specified. Panel name: '.$cms_panel_name);
			return [];
		}
		
		$this->load->model('cms/cms_panel_model');
		$config = $this->cms_panel_model->get_cms_panel_config($cms_panel_name);

		$return = [];
		
		$settings_a = $this->get_cms_page_panels_by(['panel_name' => $cms_panel_name, 'cms_page_id' => 0, 'parent_id' => 0, 'sort' => 0, ]);

		if (!empty($settings_a[0]['cms_page_panel_id'])){
			$params = $this->get_cms_page_panel_params($settings_a[0]['cms_page_panel_id'], $language);
			if (is_array($params)){
				$return = $params;
			}
		}

		// Legacy definition JSON extends
		if (!empty($config['extends'])){
			
			$extends_settings = $this->get_cms_page_panel_settings($config['extends']['panel'], $language);
			$return = array_merge_recursive_ex($extends_settings, $return);
			
		}

		// Config.json extends: merge saved settings from each source panel (e.g. timmy/shop_product)
		if (!empty($GLOBALS['config']['extends']) && is_array($GLOBALS['config']['extends'])){
			foreach ($GLOBALS['config']['extends'] as $item){
				if (($item['target'] ?? '') !== $cms_panel_name || empty($item['source'])){
					continue;
				}
				$source = $item['source'];
				if ($source === $cms_panel_name || !stristr($source, '/')){
					continue;
				}
				// Prefer current source name; fall back to legacy short name (timmy/product → timmy/shop_product)
				$source_candidates = [$source];
				if (preg_match('#^([^/]+)/shop_(.+)$#', $source, $m)){
					$source_candidates[] = $m[1].'/'.$m[2];
				}
				$source_params = [];
				foreach ($source_candidates as $candidate){
					$source_settings_a = $this->get_cms_page_panels_by([
							'panel_name' => $candidate,
							'cms_page_id' => 0,
							'parent_id' => 0,
							'sort' => 0,
					]);
					if (empty($source_settings_a[0]['cms_page_panel_id'])){
						continue;
					}
					$p = $this->get_cms_page_panel_params($source_settings_a[0]['cms_page_panel_id'], $language);
					if (is_array($p) && $p){
						$source_params = $p;
						// Auto-migrate legacy settings panel_name once
						if ($candidate !== $source){
							$this->db->query(
									'update cms_page_panel set panel_name = ? where cms_page_panel_id = ? ',
									[$source, (int)$source_settings_a[0]['cms_page_panel_id']]
									);
						}
						break;
					}
				}
				if ($source_params){
					// Source first, target wins on key clash (target already in $return)
					$return = array_merge_recursive_ex($source_params, $return);
				}
			}
		}
		
		return $return;
		
	}
	
	function shift_sort($panel_name, $start, $shift){ // panel name, start, amount
		$sql = "update `cms_page_panel` set sort = sort ".sprintf('%+d', $shift)." where panel_name = ? and sort >= ? and (cms_page_id = ? or cms_page_id = ?)";
		$query = $this->db->query($sql, array($panel_name, $start, 999999, 0, ));
	}
	
	function move_first($cms_page_panel_id){
		
		// get panel name
		$block = $this->get_cms_page_panel($cms_page_panel_id);
		
		// move first
		$this->shift_sort($block['panel_name'], 0, 1); // panel name, start, amount
		$this->update_cms_page_panel($cms_page_panel_id, ['sort' => 1]);
		
	}
	
	function get_sort_stats($panel_name){
		$sql = "select max(sort) as max_sort, count(*) as number from `cms_page_panel` where panel_name = ? and (cms_page_id = ? or cms_page_id = ?) group by panel_name ";
		$query = $this->db->query($sql, array($panel_name, 999999, 0, ));
		return $query->row_array();
	}
	
	function get_page_panel_sort_stats($page_id){
		$sql = "select max(sort) as max_sort, count(*) as number from `cms_page_panel` where cms_page_id = ? group by cms_page_id ";
		$query = $this->db->query($sql, array($page_id, ));
		$return = $query->row_array();
		if (empty($return['number'])){
			$return = array('max_sort' => 0, 'number' => 0, );
		}
		return $return;
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

		foreach($filenames_diff as $filename){
			if (file_exists($GLOBALS['config']['upload_path'].$filename)){
				unlink($GLOBALS['config']['upload_path'].$filename);
			}
		}

	}

	/**
	 * Build admin form payload for save / title preview.
	 * $input is a plain array (panel maps POST); not request-bound.
	 *
	 * @return array{data: array, data_merged: array, panel_config: array, panel_structure: array}
	 */
	function build_panel_data_for_save($input, $language){

		$this->load->model('cms/cms_panel_model');

		$block_id = !empty($input['cms_page_panel_id']) ? $input['cms_page_panel_id'] : 0;

		$data = [];
		$data['cms_page_id'] = $input['cms_page_id'] ?? null;
		$data['parent_id'] = $input['parent_id'] ?? null;
		$data['sort'] = $input['sort'] ?? null;
		$data['title'] = $input['title'] ?? null;
		$data['submenu_anchor'] = $input['submenu_anchor'] ?? null;
		$data['panel_name'] = $input['panel_name'] ?? null;
		$data['panel_params'] = $input['panel_params'] ?? [];

		if (!is_array($data['panel_params'])){
			$data['panel_params'] = [];
		}

		$panel_config = $this->cms_panel_model->get_cms_panel_config($data['panel_name']);

		if (!empty($panel_config['extends'])){
			$data['panel_params']['_extends'] = $panel_config['extends'];
		}

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

		$panel_structure = $this->cms_panel_model->get_cms_panel_edit_structure(
				$panel_config, $data['cms_page_id'], $data['parent_id'], $data['sort']);

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
				$existing = $this->get_cms_page_panel($block_id, $language, false);
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

		$title_row = $this->get_list_item_title_row($block_id, $data_merged, $language);

		if (!is_array($title_row)){
			return false;
		}

		return $this->get_list_item_title($title_row);

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

			$this->update_cms_page_panel($block_id, $data_merged, true);

		} else {

			$block_id = $this->create_cms_page_panel($data_merged);

			if (!empty($panel_config['list']['new_first'])){
				$this->move_first($block_id);
			}

		}

		if (!empty($panel_config['list']['link_target'])){

			$this->load->model('cms/cms_slug_model');

			$title_row = $this->get_list_item_title_row($block_id);
			$list_title = is_array($title_row) ? $this->get_list_item_title($title_row) : '';

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

			$parent = $this->get_cms_page_panel($data_merged['parent_id']);

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
				$this->update_cms_page_panel($data_merged['parent_id'], [$parent_name => $field_data, ]);
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

		$block = $this->get_cms_page_panel($cms_page_panel_id);

		$this->update_cms_page_panel($cms_page_panel_id, ['show' => $show, ]);

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

		$data = $this->get_cms_page_panel($cms_page_panel_id);
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
					$child_data = $this->get_cms_page_panel($child_id);
					unset($child_data['block_id']);
					unset($child_data['cms_page_panel_id']);
					$new_children[] = $this->create_cms_page_panel($child_data);
				}

				$data[$struct['name']] = $new_children;

				$all_children = $all_children + $new_children;

			}
		}

		if ($data['cms_page_id'] == 999999 || $data['cms_page_id'] == 0){
			$data['sort'] = $data['sort'] + 1;
			$this->shift_sort($data['panel_name'], $data['sort'], 1);
		}

		unset($data['block_id']);
		unset($data['cms_page_panel_id']);

		$new_block_id = $this->create_cms_page_panel($data);

		foreach($all_children as $new_child_id){
			$this->update_cms_page_panel($new_child_id, ['parent_id' => $new_block_id, ]);
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

		$panels = $this->get_cms_page_panels_by(['panel_name' => $panel_name, 'cms_page_id' => 0] + $filter);
    	
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
				$item_a = $this->get_cms_page_panels_by(array('cms_page_panel_id' => $item[$panel_name.'_id'], ));
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
			$this->_update_cached_params($row['cms_page_panel_id']);
		}

	}
	
	function _param_path_from_name($name){

		$name = (string)$name;

		if (strpos($name, 'panel_params[') !== 0){
			return $name;
		}

		if (!preg_match_all('/\[([^\]]*)\]/', $name, $matches)){
			return '';
		}

		return implode('.', $matches[1]);

	}

	function _get_param_by_path($params, $path){

		if ($path === '' || $path === null || !is_array($params)){
			return '';
		}

		$keys = explode('.', $path);
		$arr = $params;

		foreach ($keys as $key){
			if (!is_array($arr)){
				return '';
			}
			if (!array_key_exists($key, $arr)){
				if (is_numeric($key)){
					$padded = str_pad($key, 6, '0', STR_PAD_LEFT);
					if (array_key_exists($padded, $arr)){
						$arr = $arr[$padded];
						continue;
					}
				}
				return '';
			}
			$arr = $arr[$key];
		}

		if (is_array($arr)){
			return '';
		}

		return (string)$arr;

	}

	function _resolve_field_default_display($default){

		if (!isset($default) || is_array($default)){
			return '';
		}

		$default = (string)$default;

		if (substr($default, 0, 6) == ':date:'){
			$defparams = explode(':', $default);
			if (empty($defparams[3])){
				return date(substr($default, 6));
			}
			return date($defparams[2], time() + (int)$defparams[3]);
		}

		if (substr($default, 0, 5) == ':rnd:'){
			$length = (int)substr($default, 5);
			if ($length < 1){
				return '';
			}
			$chars = '0123456789abcdefghijklmnopqrstuvwxyz';
			$return = '';
			while (strlen($return) < $length){
				$pos = mt_rand(0, strlen($chars) - 1);
				$return .= $chars[$pos];
			}
			return $return;
		}

		return $default;

	}

	function _get_configured_languages(){

		$this->_ensure_language_model();

		$languages = [];

		if (!empty($GLOBALS['language']['languages']) && is_array($GLOBALS['language']['languages'])){
			foreach ($GLOBALS['language']['languages'] as $language_id => $label){
				$languages[] = [
						'language_id' => $this->cms_language_model->normalise_language_id($language_id),
						'label' => $label,
				];
			}
			return $languages;
		}

		$targets = $this->get_cms_page_panel_settings('cms/cms_targets');
		$groups = $targets['groups'] ?? [];

		if (is_array($groups)){
			foreach ($groups as $group){
				if (($group['heading'] ?? '') !== 'language' || ($group['strategy'] ?? '') !== 'language'){
					continue;
				}
				$ids = array_map('trim', explode('|', $group['settings'] ?? ''));
				$labels = array_map('trim', explode('|', $group['labels'] ?? ''));
				foreach ($ids as $key => $language_id){
					if ($language_id === ''){
						continue;
					}
					$languages[] = [
							'language_id' => $this->cms_language_model->normalise_language_id($language_id),
							'label' => $labels[$key] ?? $language_id,
					];
				}
				break;
			}
		}

		if (empty($languages)){
			$languages[] = [
					'language_id' => $this->cms_language_model->normalise_language_id($this->default_language),
					'label' => '',
			];
		}

		return $languages;

	}

	function _find_field_definition($panel_name, $path, $panel_row = []){

		$this->load->model('cms/cms_panel_model');

		$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
		$structure = $this->cms_panel_model->get_cms_panel_edit_structure(
				$config,
				$panel_row['cms_page_id'] ?? 0,
				$panel_row['parent_id'] ?? 0,
				$panel_row['sort'] ?? 0
		);

		$keys = explode('.', $path);
		$fields = $structure;
		$struct = null;

		foreach ($keys as $key){
			if ($key === '' || is_numeric($key) || preg_match('/^0+\d+$/', $key)){
				continue;
			}
			foreach ($fields as $field){
				if (($field['name'] ?? '') !== $key){
					continue;
				}
				$struct = $field;
				if (($field['type'] ?? '') === 'repeater'){
					$fields = $field['fields'] ?? [];
				}
				break;
			}
		}

		return $struct;

	}

	function get_translate_string_data($cms_page_panel_id, $field_name, $field_type = ''){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$path = $this->_param_path_from_name($field_name);

		$sql = "select panel_name, cms_page_id, parent_id, sort from cms_page_panel where cms_page_panel_id = ? limit 1 ";
		$query = $this->db->query($sql, [$cms_page_panel_id]);

		if (!$query->num_rows()){
			return ['error' => 'Panel not found'];
		}

		$row = $query->row_array();
		$params = $this->get_cms_page_panel_params($cms_page_panel_id, '');
		if (!is_array($params)){
			$params = [];
		}

		$struct = $this->_find_field_definition($row['panel_name'], $path, $row);
		$definition_default = '';
		if (is_array($struct) && array_key_exists('default', $struct)){
			$definition_default = $this->_resolve_field_default_display($struct['default']);
		}

		if ($field_type === '' && is_array($struct) && !empty($struct['type'])){
			$field_type = $struct['type'];
			if ($field_type == 'color'){
				$field_type = 'colour';
			}
		}

		$default_lang = $this->cms_language_model->normalise_language_id($this->default_language);
		$main_value = $this->_get_param_by_path($params, $path);
		$translations = $params['_translations'] ?? [];
		$stored_main = '';
		if (!empty($translations[$default_lang])){
			$stored_main = $this->_get_param_by_path($translations[$default_lang], $path);
		}
		if ($stored_main === ''){
			foreach ($translations as $lang_key => $branch){
				if ($this->cms_language_model->normalise_language_id($lang_key) === $default_lang){
					$stored_main = $this->_get_param_by_path($branch, $path);
					break;
				}
			}
		}
		$main_resolved = $stored_main !== '' ? $stored_main : $main_value;

		$other_rows = [];
		foreach ($this->_get_configured_languages() as $language_row){
			$language_id = $language_row['language_id'];
			if ($this->cms_language_model->normalise_language_id($language_id) === $default_lang){
				continue;
			}
			$stored = '';
			if (!empty($translations[$language_id])){
				$stored = $this->_get_param_by_path($translations[$language_id], $path);
			} else {
				foreach ($translations as $lang_key => $branch){
					if ($this->cms_language_model->normalise_language_id($lang_key) === $this->cms_language_model->normalise_language_id($language_id)){
						$stored = $this->_get_param_by_path($branch, $path);
						break;
					}
				}
			}
			$other_rows[] = [
					'language_id' => $language_id,
					'value' => $stored,
			];
		}

		return [
				'field_name' => $field_name,
				'field_path' => $path,
				'field_type' => $field_type,
				'definition_default' => $definition_default,
				'default_language' => $default_lang,
				'main_value' => $main_resolved,
				'other_rows' => $other_rows,
				'readonly' => !empty($struct['readonly']),
		];

	}

	function save_translate_string($cms_page_panel_id, $field_name, $values, $cms_language = ''){

		$cms_page_panel_id = (int)$cms_page_panel_id;
		$path = $this->_param_path_from_name($field_name);

		if ($cms_page_panel_id < 1 || $path === ''){
			return ['error' => 'Invalid save request'];
		}

		if (!is_array($values)){
			$values = [];
		}

		$this->_ensure_language_model();

		$default_lang = $this->cms_language_model->normalise_language_id($this->default_language);

		foreach ($values as $language_id => $value){
			$language_id = $this->cms_language_model->normalise_language_id($language_id);
			$value = is_scalar($value) ? (string)$value : '';

			if ($language_id === $default_lang){
				$this->_insert_or_update_param($cms_page_panel_id, $path, $value, 0, $default_lang);
				continue;
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

		}

		$this->_update_cached_params($cms_page_panel_id);

		if ($cms_language === null || $cms_language === ''){
			$cms_language = $this->cms_language_model->get_cms_language();
		}

		$normalised_cms = $this->cms_language_model->normalise_language_id($cms_language);
		$sync_value = null;

		foreach ($values as $language_id => $value){
			if ($this->cms_language_model->normalise_language_id($language_id) === $normalised_cms){
				$sync_value = is_scalar($value) ? (string)$value : '';
				break;
			}
		}

		return [
				'ok' => 1,
				'sync_language' => $cms_language,
				'sync_value' => $sync_value,
		];

	}

	/* DEPRECATED */
	
	/**
	 * get site default language
	 */
	function get_default_language(){
		
		return !empty($GLOBALS['config']['language']) ? $GLOBALS['config']['language'] : 'en';
		
	}
	
	/**
	 * CMS admin working language (session). Independent of visitor cookie.
	 */
	function get_cms_language(){
		
		$this->_ensure_language_model();
		return $this->cms_language_model->get_cms_language();
		
	}
	
	/**
	 * Frontend / visitor language (cookie). Do not use for CMS admin UI content.
	 */
	function get_current_language(){
		
		$this->_ensure_language_model();
		return $this->cms_language_model->get_current_language();
		
	}

	/**
	 * Content language for this request (admin → CMS session, site → visitor cookie).
	 */
	function get_content_language(){

		$this->_ensure_language_model();
		return $this->cms_language_model->get_content_language();

	}

	function is_cms_admin_request(){

		$this->_ensure_language_model();
		return $this->cms_language_model->is_cms_admin_request();

	}
	
}
