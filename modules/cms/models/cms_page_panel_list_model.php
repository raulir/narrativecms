<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Admin list UI: filtered list queries, sort/move stats.
 * Loads main cms_page_panel_model for row load/update.
 * Ticket #763.
 */
class cms_page_panel_list_model extends \Model {

	function __construct(){

		parent::__construct();
		$this->load->model('cms/cms_page_panel_model');

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
			$context['table_fields'] = $this->cms_page_panel_model->get_panel_table_fields($panel_name);
			if (!empty($context['table_fields']) && $this->cms_page_panel_model->panel_table_exists($panel_name)){
				$context['use_panel_table'] = true;
				$context['panel_table'] = $this->cms_page_panel_model->get_panel_table_name($panel_name);
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
			return count($this->cms_page_panel_model->get_cms_page_panels_by($filter));
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
			return $this->cms_page_panel_model->get_cms_page_panels_by($filter);
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


	function move_first($cms_page_panel_id){
		
		// get panel name
		$block = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		
		// move first
		$this->cms_page_panel_model->shift_sort($block['panel_name'], 0, 1); // panel name, start, amount
		$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, ['sort' => 1]);
		
	}



	function get_list_stats($panel_name){
		$sql = "select count(*) as count from `cms_page_panel` where panel_name = ? and (cms_page_id = ? or cms_page_id = ?) and `show` = 1 group by panel_name ";
		$query = $this->db->query($sql, array($panel_name, 999999, 0, ));
		return $query->row_array();
	}

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


}
