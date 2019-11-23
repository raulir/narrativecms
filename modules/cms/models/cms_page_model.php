<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_model extends CI_Model {
	
	function get_cms_pages(){
		
		// check structure
		$sql = "show columns from `cms_page` like 'position'";
		$query = $this->db->query($sql);
		if (!$query->num_rows()){
			$sql = "alter table `cms_page` add `position` varchar(20) character set ascii collate ascii_bin not null after `cms_page_id`";
			$query = $this->db->query($sql);
		}
		
		$sql = "select * from cms_page order by sort asc";
    	$query = $this->db->query($sql);
    	$result = $query->result_array();
    	
    	foreach ($result as $key => $row){
    		
    		$result[$key]['number'] = sprintf('%02d', $row['sort']);
    		$result[$key]['page_id'] = $row['cms_page_id'];
			$result[$key]['title'] = $result[$key]['slug'];
    		
    	}
    	
    	foreach ($result as $key => $row){
    		
    		if (!empty($row['meta'])){
    			$meta = json_decode($row['meta'], true);
    			if (!empty($meta)){
    				$result[$key] = array_merge($row, $meta);
    			}
    		}
    		
    	}
    	
    	return $result;
	}
	
	function get_page($cms_page_id, $language = false){
		
		if ($language == 'auto'){
			$language = !empty($GLOBALS['language']['language_id']) ? $GLOBALS['language']['language_id'] : false;
		}
		
		if (!empty($GLOBALS['language']['language_id']) && $language == $GLOBALS['language']['default']){
			$language = false;
		}
		
		$sql = "select * from cms_page where cms_page_id = ? ";
    	$query = $this->db->query($sql, array($cms_page_id));
    	
    	if ($query->num_rows()){
    	
	    	$row = $query->row_array();
	    	$row['page_id'] = $row['cms_page_id'];
	    	if (!empty($row['meta'])){
	    		$meta = json_decode($row['meta'], true);
	    		if (!empty($meta)){
	    			$row = array_merge($row, $meta);
	    		}
	    	}
	    	
	    	$return = $row;
    	
    	   	if ($language !== false){
    		
	    	    if (!empty($return['_'.$language]) && isset($return['_'.$language]['seo_title'])){
	    			$return['seo_title'] = $return['_'.$language]['seo_title'];
	    		}
	    		
	    		if (!empty($return['_'.$language]) && isset($return['_'.$language]['description'])){
	    			$return['description'] = $return['_'.$language]['description'];
	    		}
	    		
	    	}
    	
    	} else {
    		
    		$return = [];
    		
    	}
    	
    	return $return;
    	
	}
	
	function get_page_by_slug($slug){
		
		$sql = "select cms_page_id from cms_page where slug = ? ";
    	$query = $this->db->query($sql, [$slug]);
    	
    	if ($query->num_rows()){
	    	
    		$row = $query->row_array();
	    	
	    	return $this->get_page($row['cms_page_id'], 'auto');

    	} else {
    		
    		return [];
    	
    	}
    	
	}
	
	function new_page(){
		
		// get new sort
		$sql = "select max(sort) as sort from cms_page";
    	$query = $this->db->query($sql);
    	$result = $query->row_array();
		
		return array(
			'page_id' => 0,
			'cms_page_id' => 0,
			'sort' => $result['sort'] + 1,
			'slug' => '',
			'meta' => json_encode(array(
				'title' => 'New page',
				'description' => '',
				'image' => '',
			)),
			'title' => 'New page',
			'description' => '',
			'image' => '',
		);
	}
	
	function update_page($cms_page_id, $data, $language = false){
		
		if ($language !== false && $language == $GLOBALS['language']['default']){
			$language = false;
		}
		
		// check whats in meta
		$meta = array();
		
		// load old meta
		$sql = "select meta from cms_page where cms_page_id = ? ";
		$query = $this->db->query($sql, $cms_page_id);
		$meta_a = $query->row_array();
		if (!empty($meta_a['meta'])){
			$meta = (array)json_decode($meta_a['meta'], true);
		}

		if (!empty($data['meta'])){
			$meta = $meta + (array)json_decode($data['meta'], true);
		}
		unset($data['meta']);
		unset($data['cms_page_id']);
		unset($data['page_id']);
				
		foreach($data as $field => $value){
			if (!in_array($field, ['sort', 'slug', 'position'])){
				
				if ($language === false || !in_array($field, ['seo_title','description'])){
					$meta[$field] = $value;
				} else {
					$meta['_'.$language][$field] = $value;
				}
				
				unset($data[$field]);
			
			}
		}
		
		$data['meta'] = json_encode($meta);

		$sql = "update cms_page set ".implode(' = ? , ', array_keys($data))." = ? where cms_page_id = '".(int)$cms_page_id."' ";
		$this->db->query($sql, $data);
		
		return $cms_page_id;
		
	}
	
	function create_page($data){
		
		$sql = "insert into cms_page set slug = '', sort = 0, meta = '', position = '' ";
		$this->db->query($sql);
		$cms_page_id = $this->db->insert_id();
		
		$this->update_page($cms_page_id, $data);
		
		return $cms_page_id;
	
	}
	
	function save_orders($orders){
		
		foreach($orders as $key => $value){
    		$sql = "update cms_page set sort = ? where cms_page_id = ? ";
	    	$this->db->query($sql, array($value, $key, ));
		}
    	
	}
	
	function delete_page($page_id){
		
		$sql = "delete from cms_page where cms_page_id = ? ";
	    $this->db->query($sql, array($page_id, ));
	    
	    if ($page_id > 0){
	    
		    $this->load->model('cms/cms_page_panel_model');
		    $panels = $this->cms_page_panel_model->get_cms_page_panels_by(array('cms_page_id' => $page_id, ));
		    foreach($panels as $panel){
		    	$this->cms_page_panel_model->delete_cms_page_panel($panel['cms_page_panel_id']);
		    }
	    
	    }
	    
	    // delete slug
	    $this->load->model('cms/cms_slug_model');
	    $this->cms_slug_model->delete_slug($page_id);

	}
	
	function get_layouts(){
		
		$this->load->model('cms/cms_module_model');
		
		$return = array();
	
		foreach($GLOBALS['config']['modules'] as $module){
			$config = $this->cms_module_model->get_module_config($module);
			if (!empty($config['layouts']) && is_array($config['layouts'])){
				foreach($config['layouts'] as $key => $value){
					$return = array_merge($return, [['id' => $module.'/'.$value['id'], 'name' => $value['name']]]);
				}
			}
		}

		return $return;
		
	}
	
	function get_positions(){
		
		$this->load->model('cms/cms_module_model');
		
		$return = [];
		
		foreach($this->cms_module_model->get_modules() as $module){
			if ($module['active']){
				$config = $this->cms_module_model->get_module_config($module['name']);
				if (!empty($config['positions']) && is_array($config['positions'])){
					foreach($config['positions'] as $key => $value){
						if ($value['id'] !== 'main' || !empty($value['id'])){
							$return[] = array_merge($value, ['module' => $module['name']]);
						}
					}
				}
			}
		}
		
		return $return;
		
	}
	
	function get_layout_positions($layout){
		
		if (stristr($layout, '/')){
			list($module, $layout) = explode('/', $layout);
		} else {
			$module = 'cms';
		}
		
		$filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/layouts/'.$layout.'.tpl.php';
		
		$data = '_collect';
		$GLOBALS['_collect'] = [];
		
		if (file_exists($filename)){
			
			ob_start();
			include($filename);
			ob_end_clean();
			
		}

		return $GLOBALS['_collect'];
		
	}
	
}
