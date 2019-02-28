<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_slug_model extends CI_Model {
	
	function generate_page_slug($page_id, $slug_string){
		
		$this->load->model('cms/cms_page_model');

		if (empty($slug_string)){
		
			$page = $this->cms_page_model->get_page($page_id);
			$slug_string = !empty($page['seo_title']) ? $page['seo_title'] : $page['title'];

		}

		$this->delete_slug($page_id);
		
		$slug = $this->slugify_slug($slug_string);
		
		return $slug;
		
	}
	
	function generate_list_item_slug($target, $slug_string){
		
		$this->delete_slug($target);
		
		$slug = $this->slugify_slug($slug_string);
		
		return $slug;
		
	}
	
	function slugify_slug($slug_string){
		
		// diacritics
		if (strpos($string = htmlentities($slug_string, ENT_QUOTES, 'UTF-8'), '&') !== false) {
			$slug_string = html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i',
					'$1', $string), ENT_QUOTES, 'UTF-8');
		}
		
		// non alphanumeric
		$slug_string = ' '.preg_replace('/[^a-z0-9]/', '  ', strtolower($slug_string)).' ';
		
		// common words
		$slug_string = str_replace(array(' a ', ' an ', ' the ', ), '  ', $slug_string);
		// add dashes
		$slug_string = preg_replace('/[ ]+/', '-', trim($slug_string));
		// cut shorter
		if (strlen($slug_string) > 50){
			$slug = substr($slug_string, 0, 50);
			$last_pos = strrpos($slug, '-');
			$slug = substr($slug, 0, $last_pos);
		} else {
			$slug = !empty($slug_string) ? $slug_string : substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 4);
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
		
		return $final_slug;
		
	}
	
	/**
	 * 
	 * works with page_panels too
	 * 
	 * @param unknown $page_id
	 * @param unknown $slug
	 * @param int $status 0 = visible, 1 = not visible
	 * @return unknown
	 * 
	 */
	function set_page_slug($target, $slug, $status){
		
		// check if table doesnt have status field - deprecated
		$sql = "SHOW COLUMNS FROM cms_slug LIKE 'status'";
		$query = $this->db->query($sql);
		$result = $query->result_array();
		
		if (empty($result) || !count($result)){
			$sql = "ALTER TABLE cms_slug ADD `status` INT(10) UNSIGNED NOT NULL AFTER target";
			$query = $this->db->query($sql);
		}
		
		$this->delete_slug($target);
		
		// add it to table
		$sql = "insert into cms_slug set cms_slug_id = ? , target = ? , status = ? ";
		$query = $this->db->query($sql, [$slug, $target, $status, ]);

		// regenerate slug cache
		$this->_regenerate_cache();
		$this->_regenerate_sitemap();
		
		return $slug;
		
	}
	
	function delete_slug($target){

		$sql = "delete from cms_slug where target = ? ";
    	$query = $this->db->query($sql, [$target, ]);
    	
    	if (stristr($target, '/')){
    		
    		list($module, $panel) = explode('/', $target);
    		
			$sql = "delete from cms_slug where target = ? ";
    		$query = $this->db->query($sql, [$panel, ]);
    	
    	}
    	
    	// regenerate slug cache
    	$this->_regenerate_cache();
    	$this->_regenerate_sitemap();
    	 
	}
	
	/**
	 * creates new routes.php
	 */
	function _regenerate_cache(){
		
		// check if table doesnt have status field - deprecated
		$sql = "SHOW COLUMNS FROM cms_slug LIKE 'status'";
		$query = $this->db->query($sql);
		$result = $query->result_array();
		
		if (empty($result) || !count($result)){
			$sql = "ALTER TABLE cms_slug ADD `status` INT(10) UNSIGNED NOT NULL AFTER target";
			$query = $this->db->query($sql);
		}
		
		$sql = "select * from cms_slug";
    	$query = $this->db->query($sql);
    	$routes = $query->result_array();

        $data = array();
        $data[] = '<?php if ( ! defined(\'BASEPATH\')) exit(\'No direct script access allowed\');';

        if (!empty($routes )) {

            foreach ($routes as $route) {
            	if ($route['cms_slug_id'] && $route['status'] == 0){
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
    	
    	$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    	$protocol = !$secure ? 'http' : 'https';

        $data = array();
        $data[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $data[] = '<urlset xmlns="'.$protocol.'://www.sitemaps.org/schemas/sitemap/0.9">';
        
        if (!empty($routes )) {
            foreach ($routes as $route) {
            	if ($route['cms_slug_id'] && $route['status'] == 0){

            		$data[] = '<url><loc>'.$protocol.'://' . $_SERVER['HTTP_HOST'] . '/' . $route['cms_slug_id'] . '/</loc></url>';

            	}
            }
        }

        $data[] = '</urlset>';                    
        
        $output = implode("\n", $data);
        file_put_contents($GLOBALS['config']['base_path'].'cache/sitemap.xml', $output);
        
        // put it to robots.txt
        file_put_contents($GLOBALS['config']['base_path'].'robots.txt', 'Sitemap: '.$protocol.'://'.$_SERVER['HTTP_HOST'].'/cache/sitemap.xml'."\n");

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
	
	function update_slug_status($target, $status){
		
		$sql = "update cms_slug set status = ? where target = ? ";
		$query = $this->db->query($sql, [$status, $target, ]);
		
		$this->_regenerate_cache();
		$this->_regenerate_sitemap();
	
	}

}
