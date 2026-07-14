<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('array_merge_recursive_ex')){

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

class cms_page_panel_model extends Model {
	
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

	function _write_panel_table_row($cms_page_panel_id, $panel_name, $data) {
		$fields = $this->get_panel_table_fields($panel_name);
		if (empty($fields) || !$this->panel_table_exists($panel_name)) {
			return;
		}

		$row = [];
		foreach ($fields as $name => $spec) {
			if (array_key_exists($name, $data)) {
				$row[$name] = $data[$name];
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
		foreach ($fields as $name => $spec) {
			if (array_key_exists($name, $data)) {
				$table_data[$name] = $data[$name];
				unset($data[$name]);
			}
		}
		return $table_data;
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

		$ci = &get_instance();

		if (!empty($row['_extends'])){
			$files = $ci->get_panel_filenames($panel_name, $row, $row['_extends']);
		} else {
			$files = $ci->get_panel_filenames($panel_name, $row);
		}

		if (!empty($files['extends_controller'])){

			$extends_panel_name = $files['extends_module'].'_'.$files['extends_name'].'_panel';
			$ci->load->library(
					$files['extends_controller'],
					['module' => $files['extends_module'], 'name' => $files['extends_name'], ],
					$extends_panel_name
					);

			if (method_exists($ci->{$extends_panel_name}, 'panel_heading')){
				return true;
			}

		}

		if (!empty($files['controller'])){

			$panel_lib_name = $files['module'].'_'.$files['name'].'_panel';
			$ci->load->library(
					$files['controller'],
					['module' => $files['module'], 'name' => $files['name'], ],
					$panel_lib_name
					);

			return method_exists($ci->{$panel_lib_name}, 'panel_heading');

		}

		return false;

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

		if (isset($data['search_params'])){
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
			$date = new DateTime();
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

		$table_data = [];
		if ($panel_name) {
			$table_data = $this->_split_panel_table_fields($panel_name, $params);
		}

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

				if (!function_exists('recursive_keys')){
				
					// remove keys not existing in new value array
					function recursive_keys($array, $prefix = ''){
						
						$return = [];
						
						foreach($array as $k => $v){
							
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
				
				// build keys
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
		
		$this->_refresh_cached_title($cms_page_panel_id, $data['title'] ?? null);

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
		$date = new DateTime();
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

		$this->_refresh_cached_title($insert_id, $data['title'] ?? null);
		
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

		$table_join = $use_panel_table ? " join `{$panel_table}` t on t.cms_page_panel_id = a.cms_page_panel_id " : '';

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

		if (!empty($config['extends'])){
			
			$extends_settings = $this->get_cms_page_panel_settings($config['extends']['panel'], $language);
			$return = array_merge_recursive_ex($extends_settings, $return);
			
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
	
	function get_fk_data($panel_name, $filter = [], $label_field = 'title'){

		$panels = $this->get_cms_page_panels_by(['panel_name' => $panel_name, 'cms_page_id' => 0] + $filter);
    	
    	$return = array();
    	
    	foreach($panels as $row){
    		$return[(int)$row['cms_page_panel_id']] = str_replace('"', '&quot;', $row[$label_field]);
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
