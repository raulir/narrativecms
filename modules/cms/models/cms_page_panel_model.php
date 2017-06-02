<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_model extends CI_Model {
	
	/**
	 * get visible normal list members
	 */
	function get_list($panel_name, $filter = array()){

		if (!empty($filter['_sort'])){
			$_sort = $filter['_sort'];
			unset($filter['_sort']);
		}

		$return = [];
		
		$list = $this->get_cms_page_panels_by(array_merge(array('panel_name' => $panel_name, 'page_id' => [999999,0], 'show' => '1', ), $filter));
		
		foreach($list as $item){
			$return[$item['cms_page_panel_id']] = $item;
		}
		
		if (!empty($_sort)){
			$GLOBALS['_sort'] = $_sort;
			usort($return, array($this, '_list_sort'));
		}
		
		return $return;
		
	}
	
	function _list_sort($a, $b){
			
		$al = strtolower($a[$GLOBALS['_sort']]);
		$bl = strtolower($b[$GLOBALS['_sort']]);

		if ($al == $bl) {
			return 0;
		}
		
		return ($al > $bl) ? +1 : -1;
			
	}
	
	/**
	 * get available lists based on json files, SLOW!
	 */
	function get_lists(){
		
		$this->load->model('cms_panel_model');
		
		$return = [];
		
		foreach ($GLOBALS['config']['modules'] as $module){
			foreach(glob($GLOBALS['config']['base_path'].'modules/'.$module.'/definitions/*.json') as $filename){
				$list_name = basename($filename, '.json');
				$block_config = $this->cms_panel_model->get_cms_panel_config($list_name);
				if (!empty($block_config['list'])){
					$return[$list_name] = $list_name;
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
		
		return array_values($return);
		
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

	function _insert_or_update_param($cms_page_panel_id, $name, $value, $search = 0){

		if (is_array($value)){
			foreach($value as $_name => $_value){
				
				if (is_numeric($_name)){
					$_name = str_pad($_name, 3, 0, STR_PAD_LEFT);
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
				
				$this->_insert_or_update_param($cms_page_panel_id, ($name ? $name.'.' : '').$_name, $_value, $search_param);
				
			}
		} else {
			$sql = "select cms_page_panel_param_id from cms_page_panel_param where cms_page_panel_id = ? and name = ? limit 1 ";
			$query = $this->db->query($sql, array($cms_page_panel_id, $name, ));
			if ($query->num_rows()){
				$row = $query->row_array();
				$sql = "update cms_page_panel_param set value = ? , search = ? where cms_page_panel_param_id = ? ";
				$this->db->query($sql, array($value, $search, $row['cms_page_panel_param_id'], ));
			} else {
				$sql = "insert into cms_page_panel_param set cms_page_panel_id = ? , name = ? , value = ? , search = ? ";
				$this->db->query($sql, array($cms_page_panel_id, $name, $value, $search, ));
			}
		}
		
	}
	
	function _update_cached_params($cms_page_panel_id){
		
		// update cached params
		$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? and name = ? ";
		$this->db->query($sql, array($cms_page_panel_id, '',));
		
		$sql = "select * from cms_page_panel_param where cms_page_panel_id = ? ";
		$query = $this->db->query($sql, array($cms_page_panel_id, '',));
		$result = $query->result_array();
		
		$panel_params = array();
		foreach($result as $row){
// print($row['name']."\n\n");
			$keys = explode('.', $row['name']);
// print_r($keys);			
			$arr = &$panel_params;
// var_dump($arr);
			foreach ($keys as $key) {
// var_dump($panel_params);
				if (!isset($arr[$key])){
// var_dump($key);
					$arr[$key] = [];
				}
// var_dump($arr[$key]);
				$arr = &$arr[$key];
// var_dump($arr);
			}
			$arr = $row['value'];
		}
// print("---\n");		
// print_r($panel_params);		
		
		$sql = "insert into cms_page_panel_param set cms_page_panel_id = ? , name = '' , value = ? , search = 0 ";
		$this->db->query($sql, array($cms_page_panel_id, json_encode($panel_params), ));

	}
	
	function _purge_param($cms_page_panel_id){
		$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? ";
		$this->db->query($sql, array($cms_page_panel_id, ));
	}
	
	function get_cms_page_panel_params($cms_page_panel_id){
		
		$sql = "select value from cms_page_panel_param where cms_page_panel_id = ? and name = ''";
    	$query = $this->db->query($sql, array($cms_page_panel_id));
    	if ($query->num_rows()){
	    	$row = $query->row_array();
    		return json_decode($row['value'], true);
    	} else {
    		return array();
    	}
		
	}
	
	// deprecated, used only in cms_main_menu.php
	function get_cms_page_panels(){
		
		$sql = "select * from block order by sort asc";
    	$query = $this->db->query($sql);
    	$result = $query->result_array();
    	
    	foreach ($result as $name => $row){
    		
    		$result[$name]['panel_params'] = $this->get_cms_page_panel_params($row['block_id']);
    		
    	}
    	
    	return $result;
	
	}

	function get_cms_page_panel($cms_page_panel_id){
	
		$sql = "select *, block_id as cms_page_panel_id from block where block_id = ? ";
		$query = $this->db->query($sql, array($cms_page_panel_id));
		$row = $query->row_array();
		 
		if (empty($row['block_id'])) {
			return false;
		}

		$panel_params = $this->get_cms_page_panel_params($row['block_id']);
	    
		if (is_array($panel_params)){
			$return = array_merge($panel_params, $row);
		} else {
			$return = $row;
		}
		
		// add settings if present
		$return = array_merge($this->cms_page_panel_model->get_cms_page_panel_settings($return['panel_name']), $return);
	
		return $return;
	
	}
	
	function new_block(){
		
		$sql = "select max(sort) as sort from block";
    	$query = $this->db->query($sql);
    	$result = $query->row_array();
		
		return array(
			'block_id' => 0,
			'page_id' => 0,
			'parent_id' => 0,
			'show' => 0,
			'sort' => $result['sort'] + 1,
			'title' => 'New block',
			'panel_name' => '',
			'submenu_anchor' => '',
			'submenu_title' => '',
		);
		
	}
	
	function update_cms_page_panel($cms_page_panel_id, $data, $no_purge = false){

		if (isset($data['search_params'])){
			$search_params = $data['search_params'];
			unset($data['search_params']);
		} else {
			$search_params = 0;
		}
		
		$params = !empty($data['panel_params']) ? $data['panel_params'] : array();

		if (isset($data['panel_params'])){
			unset($data['panel_params']);
		}
		
		// new params stuff
		foreach($data as $key => $value){
			if (!in_array($key, array('block_id', 'page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title', ))){
				$params[$key] = $value;
				unset($data[$key]);
			}
		}

		if (!empty($params)){
			
			// detailed data
			if (!$no_purge){
				$this->_purge_param($cms_page_panel_id);
			}
			
			$this->_insert_or_update_param($cms_page_panel_id, '', $params, $search_params);
			$this->_update_cached_params($cms_page_panel_id);
		
		}

		if(!empty($data)){
			$sql = "update block set `".implode('` = ? , `', array_keys($data))."` = ? where block_id = '".(int)$cms_page_panel_id."' ";
			$this->db->query($sql, $data);
		}
		
		$this->invalidate_html_cache($cms_page_panel_id);
		
	}
	
	function invalidate_html_cache($cms_page_panel_id){
		
		$filestart = $GLOBALS['config']['base_path'].'cache/_'.(int)$cms_page_panel_id.'_';
		array_map('unlink', glob($filestart.'*'));
		
		// if list item, invalidate all related panel caches as well
		$block = $this->get_cms_page_panel($cms_page_panel_id);
		if ($block['page_id'] == 999999 || $block['page_id'] === 0){
			$lists = $this->get_lists();
			if (in_array($block['panel_name'], $lists)){
				
				$sql = "select cms_page_panel_id from cms_page_panel_param where value = '%" . $block['panel_name'] . "%' and name = '_cache_lists'";
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
		
		if (empty($data['page_id'])){
			$data['page_id'] = 0;
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

		$panel_params = array();
		
		foreach($data as $key => $value){
			if (!in_array($key, array('block_id', 'page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title', ))){
				$panel_params[$key] = $value;
				unset($data[$key]);
			}
		}
		
		// check sort
		if (!empty($data['sort']) && $data['sort'] == 'first'){
			$this->shift_sort($data['panel_name'], 0, 1);
			$data['sort'] = 1;
		} elseif (!isset($data['sort']) && ($data['page_id'] == '999999' || $data['page_id'] == 0)) {
			$sort_stats = $this->get_sort_stats($data['panel_name']);
			$data['sort'] = !empty($sort_stats['max_sort']) ? $sort_stats['max_sort'] + 1 : 0;
		} elseif (!empty($data['sort']) && $data['sort'] == 'last'){
			$sort_stats = $this->get_page_panel_sort_stats($data['page_id']);
			$data['sort'] = $sort_stats['max_sort'] + 1;
		}// else stays the same
		
		$sql = "insert into block set `".implode('` = ? , `', array_keys($data))."` = ? ";
		$this->db->query($sql, $data);
		
		$insert_id = $this->db->insert_id();
		
		if (!empty($panel_params)){
		
			// detailed data
			$this->_insert_or_update_param($insert_id, '', $panel_params, $search_params);
			$this->_update_cached_params($insert_id);
		}
		
		$this->invalidate_html_cache($insert_id);
		
		return $insert_id;
		
	}
	
	/*
	 * old stuff, but used still
	 */
	function create_block($data){
		
		// $data['panel_params'] = json_encode($data['panel_params']);
		
		$panel_params = $data['panel_params'];
		unset($data['panel_params']);
		
		$search_params = $data['search_params'];
		unset($data['search_params']);
		
		$sql = "insert into block set `".implode('` = ? , `', array_keys($data))."` = ? ";
		$this->db->query($sql, $data);
		
		$insert_id = $this->db->insert_id();
		
		if (!empty($panel_params)){
		
			// detailed data
			$this->_insert_or_update_param($insert_id, '', $panel_params, $search_params);
			$this->_update_cached_params($insert_id);
			
		}

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
    		$sql = "update block set sort = ? where block_id = ? ";
	    	$this->db->query($sql, array($value, $name, ));
		}
    	
	}
	
	function delete_cms_page_panel($cms_page_panel_id){
		
		if (empty($cms_page_panel_id)){
			error_log('Deleting empty block '.serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
			return;
		}
		
		// delete children
		$sql = "select block_id from block where parent_id = ? ";
   		$query = $this->db->query($sql, array($cms_page_panel_id));
    	$result = $query->result_array();
    	foreach($result as $child){
    		$this->delete_cms_page_panel($child['block_id']);
    	}
		
		// update parent
	    $cms_page_panel = $this->get_cms_page_panel($cms_page_panel_id);
	    
	    if ($cms_page_panel){
	    	if (!empty($cms_page_panel['parent_id'])){
	    		$parent = $this->get_cms_page_panel($cms_page_panel['parent_id']);
	    		if (!empty($parent['panel_name'])){
	    			$this->load->model('cms_panel_model');
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
    							$this->update_cms_page_panel($cms_page_panel['parent_id'], array($item['name'] => $children, ), true);
							}
						}
					}
	    		}
	    	}
	    }
		
		$sql = "delete from block where block_id = ? ";
	    $this->db->query($sql, array($cms_page_panel_id, ));
		
		$sql = "delete from cms_page_panel_param where cms_page_panel_id = ? ";
	    $this->db->query($sql, array($cms_page_panel_id, ));
	    
	    // shortcuts
	    $sql = "delete from block where panel_name = ? ";
	    $this->db->query($sql, array($cms_page_panel_id, ));
	    
	    // delete slug pointing to this panel
	    $this->load->model('cms_slug_model');
	    $this->cms_slug_model->delete_slug($cms_page_panel['panel_name'].'='.$cms_page_panel_id);
	    
		$this->invalidate_html_cache($cms_page_panel_id);
	
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
			$sql = "select count(*) as total from `block` where `" . preg_replace("/[^A-Za-z0-9_=?` ]/", '', implode('` = ? and `', $fields)) . "` = ? ";
			
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
		
		if (isset($filter['cms_page_panel_id'])){
			$filter['block_id'] = $filter['cms_page_panel_id'];
			unset($filter['cms_page_panel_id']);
		}
		
		if (isset($filter['cms_page_id'])){
			$filter['page_id'] = $filter['cms_page_id'];
			unset($filter['cms_page_id']);
		}
		
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
				
		// separate filters
		$sql_filter = array();
		$params_filter = array();
		$sql_arrays = array();
		$sql_filter_str = '';
		
		// to support module/panel_name panel names, DEPRECATE in future - all panel names should have module name
		if (!empty($GLOBALS['config']['deprecated']) && !empty($filter['panel_name'])){

			// make this an array
			if (!is_array($filter['panel_name'])){
				$filter['panel_name'] = [$filter['panel_name']];
			}
			
			$new_panel_name_filter = [];
			
			foreach($filter['panel_name'] as $key => $panel_name_original){
				
				$new_panel_name_filter[] = $panel_name_original;
				
				if (stristr($panel_name_original, '/')){

					// extract name without module name
					list($module, $panel_name) = explode('/', $panel_name_original);
					$new_panel_name_filter[] = $panel_name;

				} else {
					
					// combine with all possible module names
					foreach($GLOBALS['config']['modules'] as $module){
						$new_panel_name_filter[] = $module.'/'.$panel_name_original;
					}

				}
			
			}
			
			$filter['panel_name'] = $new_panel_name_filter;
					
		}

		foreach($filter as $key => $value){
			$tkey = str_replace('!', '', $key);
			if (in_array($tkey, array('block_id', 'page_id', 'parent_id', 'show', 'sort', 'title', 'panel_name', 'submenu_anchor', 'submenu_title', ))){
				
				// check for arrays in sql filter
				if (!is_array($value)){
					$sql_filter[$key] = $value;
				} else if (!empty($value) && is_array($value)){
					
					// sanitise the array
					$new_array = array();
					foreach($value as $el){
						$new_array[] = str_replace(array("'", '"'), array("\\\'", '\\\"'), $el);
					}
					
					$sql_arrays[] = " `".$tkey."` ".($tkey != $key ? ' not ' : '')." in ('".implode("','", $new_array)."') ";
				}
			
			} else {
				$params_filter[$key] = $value;
			}
		}

		$fields = array_keys($sql_filter);
		if (!empty($fields)){
			$sql_filter_str = $sql_filter_str . " `" . preg_replace("/[^A-Za-z0-9_=?!` ]/", '', implode('` = ? and `', $fields)) . "` = ? ";
		}
		
		if(!empty($sql_arrays)){
			$sql_arrays_str = implode(' and ', $sql_arrays);
		} else {
			$sql_arrays_str = '';
		}
		
		$sql = "select a.*, b.value as _params, a.block_id as cms_page_panel_id from `block` a left join cms_page_panel_param b on b.name = '' and b.cms_page_panel_id = a.block_id " .
				" where ".$sql_filter_str." ".(!empty($sql_filter_str) && !empty($sql_arrays_str) ? ' and ' : '')." ".$sql_arrays_str." order by sort ".$order;
		$sql = str_replace('!` =', '` !=', $sql); // not query

		$query = $this->db->query($sql, $sql_filter);
    	if ($query->num_rows()){
	    	$return = $query->result_array();
    	} else {
    		$return = array();
    	}
    	
    	// unpack params    	  	
    	foreach ($return as $key => $row){
    		if(!empty($row['_params'])){
    			$_params = json_decode($row['_params'], true);
    			if (is_array($_params)){
	    			$return[$key] = array_merge($_params, $row);
    			}
    		}
    		unset($return[$key]['_params']);
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
    	foreach($return as $key => $cms_page_panel){
    		if ($cms_page_panel['page_id']){
    			$return[$key] = array_merge($this->get_cms_page_panel_settings($cms_page_panel['panel_name']), $cms_page_panel);
   			}
    	}

		return $return;
    
	}
	
	/**
	 * 
	 * @param unknown $cms_panel_name - module/panel_name
	 */
	function get_cms_page_panel_settings($cms_panel_name){
		
		$settings_a = $this->get_cms_page_panels_by(['panel_name' => $cms_panel_name, 'cms_page_id' => 0, 'parent_id' => 0, 'sort' => 0, ]);
		 
		if (!empty($settings_a[0])){
			return $settings_a[0];
		}
		
		return [];
		
	}
	
	function shift_sort($panel_name, $start, $shift){ // panel name, start, amount
		$sql = "update `block` set sort = sort ".sprintf('%+d', $shift)." where panel_name = ? and sort >= ? and (page_id = ? or page_id = ?)";
		$query = $this->db->query($sql, array($panel_name, $start, 999999, 0, ));
	}
	
	function move_first($cms_page_panel_id){
		
		// get panel name
		$block = $this->get_cms_page_panel($cms_page_panel_id);
		
		// move first
		$this->shift_sort($block['panel_name'], 0, 1); // panel name, start, amount
		$this->update_cms_page_panel($cms_page_panel_id, array('sort' => 1, ), true);
		
	}
	
	function get_sort_stats($panel_name){
		$sql = "select max(sort) as max_sort, count(*) as number from `block` where panel_name = ? and (page_id = ? or page_id = ?)group by panel_name ";
		$query = $this->db->query($sql, array($panel_name, 999999, 0, ));
		return $query->row_array();
	}
	
	function get_page_panel_sort_stats($page_id){
		$sql = "select max(sort) as max_sort, count(*) as number from `block` where page_id = ? group by page_id ";
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
	
	/* deprecated */
	function get_blocks_by($filter){
		return $this->get_cms_page_panels_by($filter);
	}
	
	function get_fk_data($panel_name, $filter = array(), $label_field = 'title'){

		$panels = $this->get_cms_page_panels_by(array('panel_name' => $panel_name, 'page_id' => [999999,0], ) + $filter);
    	
    	$return = array();
    	
    	foreach($panels as $row){
    		$return[(int)$row['block_id']] = str_replace('"', '&quot;', $row[$label_field]);
    	}
    	
    	return $return;
	
	}
	
	function extend_fk_repeater($panel_name, $data){
		
		foreach($data as $key => $item){
			if (!empty($item)){
				$item_a = $this->get_cms_page_panels_by(array('block_id' => $item[$panel_name.'_id'], ));
				if (!empty($item_a[0])){
					$data[$key] = $item_a[0];
				}
			}
		}

		return $data;
		
	}

	function get_max_cms_page_panel_id($panel_name){
	
		$sql = "select max(block_id) as cms_page_panel_id from block where panel_name = ? and (page_id = '999999' or page_id = 0) ";
		$query = $this->db->query($sql, array($panel_name, ));
		$return = $query->row_array();
	
		return $return['cms_page_panel_id'];
	
	}
	
	function update_block($cms_page_panel_id, $data, $no_purge = false){
	
		$this->update_cms_page_panel($cms_page_panel_id, $data, $no_purge);
	
	}
	
}
