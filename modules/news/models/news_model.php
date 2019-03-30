<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class news_model extends CI_Model {

    function get_next($current_sort){
    	
    	$sql = "select block_id from block where panel_name = 'article' and page_id = '999999' and sort > ? order by sort limit 1";
     	$query = $this->db->query($sql, array($current_sort, ));
     	if ($query->num_rows()){
     		$return = $query->row_array();
     	}
     	
     	return !empty($return['block_id']) ? $return['block_id'] : 0;
   	
    }
    
    function get_comment_count($article_id){
    	
    	$sql = "select count(*) as number from news_comment where news_article_id = ? and hidden = '0' ";
     	$query = $this->db->query($sql, array($article_id, ));
   		$return = $query->row_array();
     	
     	return $return['number'];
    	
    }
    
    function get_comments_by_article($article_id){
    	
    	$sql = "select * from news_comment where news_article_id = ? and hidden = '0' ";
     	$query = $this->db->query($sql, array($article_id, ));
   		$return = $query->result_array();
     	
     	return $return;
    
    }
    
    function insert_comment($article_id, $text, $name){
    	$sql = "insert into news_comment set news_article_id = ? , text = ? , author = ? , moderated = 0 , hidden = 0 , posted_date = ? , ip = ? ";
     	$this->db->query($sql, array($article_id, $text, $name, date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR']));
    }

}
