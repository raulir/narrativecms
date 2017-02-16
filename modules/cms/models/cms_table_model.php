<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_table_model extends CI_Model {

	function get_data($table){
		
		$sql = "select * from `".$table."` order by sort asc";
    	$query = $this->db->query($sql);
    	$result = $query->result_array();
    	
    	return $result;
	
	}

	function update_row($table, $row){
		
		$sql = "update `".$table."` set ".implode(' = ? , ', array_keys($row))." = ? where `".$table."_id` = '".(int)$row[$table.'_id']."' ";
		$this->db->query($sql, $row);
	
	}

	function insert_row($table, $row){
		
		unset($row[$table.'_id']);
		
		$sql = "insert into `".$table."` set ".implode(' = ? , ', array_keys($row))." = ?";
		$this->db->query($sql, $row);
		$return = $this->db->insert_id();
		return $return;

	}

	function delete_rows($table, $ids, $file_fields = array()){
		
		// delete files first
		foreach($file_fields as $field){
			$sql = "select ".$field." from `".$table."` where ".$table."_id not in ('".implode("','", $ids)."')";
   			$query = $this->db->query($sql);
    		$result = $query->result_array();
 			foreach($result as $row){
 				if (file_exists($GLOBALS['config']['upload_path'].$row[$field]) && is_file($GLOBALS['config']['upload_path'].$row[$field])){
 					unlink($GLOBALS['config']['upload_path'].$row[$field]);
 				}
 			}
		}

		$sql = "delete from `".$table."` where ".$table."_id not in ('".implode("','", $ids)."')";
    	$query = $this->db->query($sql);

	}
	
	function get_fk_data($table){
	
		$sql = "select ".$table."_id, name from ".$table." order by sort asc";
    	$query = $this->db->query($sql);
    	$result = $query->result_array();
    	
    	$return = array();
    	
    	foreach($result as $row){
    		$return[$row[$table.'_id']] = $row['name'];
    	}
    	
    	return $return;
	
	}
	
}
