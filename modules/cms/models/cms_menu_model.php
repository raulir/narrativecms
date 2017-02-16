<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_menu_model extends CI_Model {
	
	function get_menu_items($menu_id){
		$sql = "select * from menu_item where menu_id = ? order by sort asc";
    	$query = $this->db->query($sql, array($menu_id, ));
    	$result = $query->result_array();
    	
    	return $result;
	}
	
	function get_menu_item($menu_item_id){
		$sql = "select * from menu_item where menu_item_id = ? ";
    	$query = $this->db->query($sql, array($menu_item_id));
    	if ($query->num_rows()){
    		$row = $query->row_array();
    		return $row;
    	} else {
    		return array();
    	}
	}
		
	function new_menu_item($menu_id){
		
		$sql = "select max(sort) as sort from menu_item where menu_id = ? ";
    	$query = $this->db->query($sql, array($menu_id));
    	$result = $query->row_array();
		
		return array(
			'menu_item_id' => 0,
			'menu_id' => $menu_id,
			'mode' => 1,
			'sort' => $result['sort'] + 1,
			'text' => 'New menu item',
			'link' => '',
			'new_window' => 0,
			'hide_from_menu' => 0,
		);
	}
	
	function update_menu_item($menu_item_id, $data){

		$sql = "update menu_item set ".implode(' = ? , ', array_keys($data))." = ? where menu_item_id = '".(int)$menu_item_id."' ";
		$this->db->query($sql, $data);
		
	}
	
	function create_menu_item($data){
		$sql = "insert into menu_item set ".implode(' = ? , ', array_keys($data))." = ? ";
		$this->db->query($sql, $data);
		return $this->db->insert_id();
	}
	
	function get_menu_items_by($filter){
		
		$this->load->model('cms_page_model');
	
		$fields = array_keys($filter);
		
		$sql = "select * from `menu_item` where " . preg_replace("/[^A-Za-z0-9_=? ]/", '', implode(' = ? and ', $fields)) . " = ? order by sort ";
    	$query = $this->db->query($sql, $filter);
    	if ($query->num_rows()){
	    	$return = $query->result_array();
    	} else {
    		$return = array();
    	}
    	
    	// get page ids
    	foreach($return as $key => $row){
    		
    		if (stristr($row['link'], '#')){
    			list($page_slug, $anchor_slug) = explode('#', $row['link']);
    		} else {
    			$page_slug = $row['link'];
    			$anchor_slug = '';
    		}
    		
  			$page_slug = trim($page_slug, '/');
 
    		if (empty($page_slug)){
   				$return[$key]['cms_page_id'] = 1;
   				$return[$key]['cms_page_panel_id'] = 1;   				
    		} else {
    			$page = $this->cms_page_model->get_page_by_slug($page_slug);
    			$return[$key]['cms_page_id'] = !empty($page['cms_page_id']) ? $page['cms_page_id'] : 0 ;
    			
    			if (!empty($anchor_slug)){
    				// submenu_anchor
    				// TODO: get correct panel!
   					$return[$key]['cms_page_panel_id'] = 0;   				
    			} else {
   					$return[$key]['cms_page_panel_id'] = 0;   				
    			}
    			
    		}
    		
    	}
 
   		return $return;
    
	}
	
	function delete_menu_item($menu_item_id){
		
		$sql = "delete from menu_item where menu_item_id = ? ";
	    $this->db->query($sql, array($menu_item_id, ));
		
	}
	
}
