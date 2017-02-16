<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_slug_model extends CI_Model {
	
	/**
	 * 
	 * accepts targets: 
	 * number => page_id 
	 * string=number => page type with this id ( "article=22" ) 
	 * 
	 */
	function request_slug($target, $heading = false){
		
		// page id 1 always has empty slug
		if ($target === 1 || $target === '1'){
			return '';
		}
		
		if ($heading !== false && $heading[0] === '_'){
			$heading_field = substr($heading, 1);
			$heading = false;
		} else {
			$heading_field = 'heading';
		}
		
		if ($heading === false){

			// get panel anchor
			list($panel_name, $cms_page_panel_id) = explode('=', $target);
			
			// if new panel with no header information
			$sql = "select value from cms_page_panel_param where cms_page_panel_id = ? and name = ? ";
			$query = $this->db->query($sql, array($cms_page_panel_id, $heading_field, ));
			$param_a = $query->row_array();

			$heading = !empty($param_a['value']) ? $param_a['value'] : '';
			
			if(empty($heading)){
				$sql = "select * from block where block_id = ? limit 1 ";
				$query = $this->db->query($sql, array($cms_page_panel_id, ));
				$row = $query->row_array();
				if (!empty($row['submenu_anchor'])){
					$heading = $row['submenu_anchor'];
				} else if(!empty($row['title'])){
					$heading = $row['title'];
				}
			}
			
		}
		
		// normalise target
		$target = trim(str_replace('index/index/', '', $target), '/');
		
		// check if slug already exists for the target
		$sql = "select * from cms_slug where target = ? limit 1 ";
		$query = $this->db->query($sql, array($target, ));
    	
    	if ($query->num_rows() && $heading === false){
	    	$row = $query->row_array();
	    	return $row['cms_slug_id'];
    	} else {
    		$sql = "delete from cms_slug where target = ? limit 1 ";
    		$this->db->query($sql, array($target, ));
    	}
    	
    	// if no slug for the url
   		// diacritics
   		if (strpos($string = htmlentities($heading, ENT_QUOTES, 'UTF-8'), '&') !== false) {
        	$heading = html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', 
					'$1', $string), ENT_QUOTES, 'UTF-8');
    	}
    	// non alphanumeric
    	$heading = ' '.preg_replace('/[^a-z0-9]/', '  ', strtolower($heading)).' ';
    	// common words
		$heading = str_replace(array(' a ', ' an ', ' the ', ), '', $heading);
		// add dashes
		$heading = preg_replace('/[ ]+/', '-', trim($heading));
		// cut shorter
		if (strlen($heading) > 50){
			$slug = substr($heading, 0, 50);
			$last_pos = strrpos($slug, '-');
			$slug = substr($slug, 0, $last_pos);
		} else {
			$slug = !empty($heading) ? $heading : substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
		}
		
		$final_slug = $slug;
		$i = 1;
		$ok = false;
		// check if exists
		while(!$ok){
			$sql = "select * from cms_slug where cms_slug_id = ? limit 1";
			$query = $this->db->query($sql, array($final_slug, ));
			if ($query->num_rows() /* || in_array($final_slug, $GLOBALS['config']['modules']) */){
				$final_slug = $slug.'-'.$i;
				$i = $i + 1;
    		} else {
    			$ok = true;
    		}	
		}

		// add it to table
		$sql = "insert into cms_slug set cms_slug_id = ? , target = ? ";
    	$query = $this->db->query($sql, array($final_slug, $target, ));
    	
    	// regenerate slug cache
    	$this->_regenerate_cache();
    	$this->_regenerate_sitemap();
    	 
    	return $final_slug;

	}
	
	function delete_slug($target){
		
		$sql = "delete cms_slug where target = ? ";
    	$query = $this->db->query($sql, array($target, ));
    	
    	// regenerate slug cache
    	$this->_regenerate_cache();
    	$this->_regenerate_sitemap();
    	 
	}
	
	/**
	 * creates new routes.php
	 */
	function _regenerate_cache(){
		
    	$sql = "select * from cms_slug";
    	$query = $this->db->query($sql);
    	$routes = $query->result_array();

        $data = array();
        $data[] = '<?php if ( ! defined(\'BASEPATH\')) exit(\'No direct script access allowed\');';

        if (!empty($routes )) {

            foreach ($routes as $route) {
            	if ($route['cms_slug_id']){
                	$data[] = '$route[\'' . $route['cms_slug_id'] . '\'] = \'index/index/' . $route['target'] . '/\';';
            	}
            }
        }
                    
        $output = implode("\n", $data);
        file_put_contents($GLOBALS['config']['base_path'] . 'cache/routes.php', $output);
    
	}
	
	/**
	 * creates new sitemap.xml and robots.txt
	 */
	function _regenerate_sitemap(){
		
    	$sql = "select * from cms_slug";
    	$query = $this->db->query($sql);
    	$routes = $query->result_array();

        $data = array();
        $data[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $data[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        if (!empty($routes )) {
            foreach ($routes as $route) {
            	if ($route['cms_slug_id']){

            		$data[] = '<url><loc>http://' . $_SERVER['HTTP_HOST'] . '/' . $route['cms_slug_id'] . '/</loc></url>';

            	}
            }
        }

        $data[] = '</urlset>';                    
        
        $output = implode("\n", $data);
        file_put_contents($GLOBALS['config']['base_path'].'cache/sitemap.xml', $output);
        
        // put it to robots.txt
        file_put_contents($GLOBALS['config']['base_path'].'robots.txt', 'Sitemap: http://'.$_SERVER['HTTP_HOST'].'/cache/sitemap.xml'."\n");

	}
	
	function get_cms_slug_by_target($target){
		$sql = "select * from cms_slug where target = ? limit 1 ";
    	$query = $this->db->query($sql, array($target, ));
    	if ($query->num_rows()){
    		$row = $query->row_array();
    		return $row['cms_slug_id'];
    	} else {
    		return '';
    	}
	}
	
}