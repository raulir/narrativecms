<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_slug_model extends Model {
	
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

		if (substr($target, 0, 2) == '_/'){
			str_replace('_/', end($GLOBALS['config']['modules']).'/', $target);
		}

		$this->delete_slug($target);
		
		$slug = $this->slugify_slug($slug_string);
		
		return $slug;
		
	}
	
	function _slugify_candidate($slug_string){

		$slug_string = trim((string)$slug_string);

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

		return $slug;

	}

	function slugify_slug($slug_string){

		$slug = $this->_slugify_candidate($slug_string);

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
		
		if (substr($target, 0, 2) == '_/'){
			str_replace('_/', end($GLOBALS['config']['modules']).'/', $target);
		}
		
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
	
	function get_slug_row_by_target($target){

		$target = trim((string)$target);

		if ($target === ''){
			return false;
		}

		$sql = 'select * from cms_slug where target = ? limit 1';
		$query = $this->db->query($sql, array($target));

		if (!$query->num_rows()){
			return false;
		}

		return $query->row_array();

	}

	function get_cms_slug_by_target($target){

		$row = $this->get_slug_row_by_target($target);

		if ($row === false){
			return '';
		}

		return $row['cms_slug_id'];

	}

	function get_target_by_slug_id($slug_id){

		$slug_id = trim((string)$slug_id);

		if ($slug_id === ''){
			return '';
		}

		$sql = 'select target from cms_slug where cms_slug_id = ? limit 1';
		$query = $this->db->query($sql, array($slug_id));

		if (!$query->num_rows()){
			return '';
		}

		$row = $query->row_array();

		return $row['target'] ?? '';

	}

	function check_slug_for_edit($raw_slug, $current_slug, $target){

		$raw_slug = trim((string)$raw_slug);
		$current_slug = trim((string)$current_slug);
		$target = trim((string)$target);

		if ($raw_slug === ''){
			return array(
				'check_status' => '',
				'check_message' => '',
				'candidate' => '',
				'ok' => 0,
			);
		}

		$candidate = $this->_slugify_candidate($raw_slug);

		if ($raw_slug !== $candidate){
			return array(
				'check_status' => 'error',
				'check_message' => 'Disallowed characters',
				'candidate' => $candidate,
				'ok' => 0,
			);
		}

		if ($candidate === $current_slug){
			return array(
				'check_status' => 'success',
				'check_message' => 'Slug available',
				'candidate' => $candidate,
				'ok' => 1,
			);
		}

		$existing_target = $this->get_target_by_slug_id($candidate);

		if ($existing_target !== '' && $existing_target !== $target){
			return array(
				'check_status' => 'error',
				'check_message' => 'Slug taken',
				'candidate' => $candidate,
				'ok' => 0,
			);
		}

		return array(
			'check_status' => 'success',
			'check_message' => 'Slug available',
			'candidate' => $candidate,
			'ok' => 1,
		);

	}

	function rename_target_slug($target, $raw_slug){

		$row = $this->get_slug_row_by_target($target);

		if ($row === false){
			return array('ok' => 0, 'error' => 'Slug not found');
		}

		$current_slug = $row['cms_slug_id'] ?? '';
		$check = $this->check_slug_for_edit($raw_slug, $current_slug, $target);

		if (empty($check['ok'])){
			$error = !empty($check['check_message']) ? $check['check_message'] : 'Invalid slug';
			return array('ok' => 0, 'error' => $error);
		}

		$new_slug = $check['candidate'];

		if ($new_slug === $current_slug){
			return array('ok' => 1, 'slug' => $new_slug, 'changed' => 0);
		}

		$status = isset($row['status']) ? (int)$row['status'] : 0;
		$this->set_page_slug($target, $new_slug, $status);

		return array('ok' => 1, 'slug' => $new_slug, 'changed' => 1, 'old_slug' => $current_slug);

	}
	
	function update_slug_status($target, $status){
		
		$sql = "update cms_slug set status = ? where target = ? ";
		$query = $this->db->query($sql, [$status, $target, ]);
		
		$this->_regenerate_cache();
		$this->_regenerate_sitemap();
	
	}
	
	function get_target_by_cms_slug($slug){
		
		$sql = "select * from cms_slug where cms_slug_id = ? and status = '0' limit 1 ";
		$query = $this->db->query($sql, [$slug, ]);
		if ($query->num_rows()){
			
			$row = $query->row_array();
			
			if (stristr($row['target'], '=')){
				
				list($rest, $return) = explode('=', $row['target']);
				return $return;
				
			}
			
			return $row['target'];
			
		} else {
			return 0;
		}
		
	}

}
