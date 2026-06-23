<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_text_model extends CI_Model {

	function get_cms_text($cms_text_id){

		$return = [];

		$sql = 'select * from cms_text where cms_text_id = ? ';
		$query = $this->db->query($sql, array($cms_text_id));

		if ($query->num_rows()){
			$return = $query->row_array();
		}

		return $return;

	}

}