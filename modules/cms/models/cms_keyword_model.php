<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_keyword_model extends CI_Model {

	function get_cms_keywords($params = array()){

		if (function_exists('mysql_set_charset')){
			@mysql_set_charset('utf8mb4');
		}
		
		$sql = "select * from cms_keyword order by cms_keyword_id asc";
		
		if(!empty($params['_start']) && !empty($params['_limit'])){
			$sql .= " limit ".(int)$params['_start']." offset ".(int)$params['_start'];
		}
		
    	$query = $this->db->query($sql);
    	$result = $query->result_array();
    	
    	return $result;

	}
	
	function count_cms_keywords($params){

		$sql = "select count(*) as total from cms_keyword";
		
    	$query = $this->db->query($sql);
    	$row = $query->row_array();
    	
    	return $row['total'];

	}
	
	function create_cms_keyword($cms_keyword_id, $data) {
		
		$sql = "select * from cms_keyword where cms_keyword_id = ? ";
		$query = $this->db->query($sql, array($cms_keyword_id, ));
		$row = $query->row_array();
		if (!empty($row['cms_keyword_id'])){
			return false;
		}
		
		$sql = "insert into cms_keyword set cms_keyword_id = ? ";
    	$query = $this->db->query($sql, array($cms_keyword_id, ));
    	
    	return true;
    	
	}
	
	function update_cms_keyword($cms_keyword_id, $data) {
		
		$sql = "select * from cms_keyword where cms_keyword_id = ? ";
		$query = $this->db->query($sql, array($data['keyword'], ));
		$row = $query->row_array();
		if (!empty($row['cms_keyword_id'])){
			return false;
		}
		
		$sql = "update cms_keyword set cms_keyword_id = ? where cms_keyword_id = ? ";
    	$query = $this->db->query($sql, array($data['keyword'], $cms_keyword_id, ));
    	
    	return true;
    	
	}
	
	function delete_cms_keyword($cms_keyword_id){
		
		$sql = "delete from cms_keyword where cms_keyword_id = ? ";
    	$query = $this->db->query($sql, array($cms_keyword_id, ));
		
	}

}
