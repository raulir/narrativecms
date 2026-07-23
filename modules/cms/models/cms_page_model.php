<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_model extends \Model {
	
	function get_cms_pages(){

		$this->_ensure_cms_page_schema();
		
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

	function _ensure_cms_page_schema(){

		$flag = $GLOBALS['config']['base_path'].'cache/schema_cms_page.ok';

		if (file_exists($flag)){
			return;
		}

		$sql = "show columns from `cms_page` like 'position'";
		$query = $this->db->query($sql);
		if (!$query->num_rows()){
			$sql = "alter table `cms_page` add `position` varchar(20) character set ascii collate ascii_bin not null after `cms_page_id`";
			$query = $this->db->query($sql);
		}

		$sql = "show columns from `cms_page` like 'title'";
		$query = $this->db->query($sql);
		if ($query->num_rows()){
			$sql = "alter table `cms_page` drop column `title`";
			$query = $this->db->query($sql);
		}
		
		$sql = "show columns from `cms_page` like 'description'";
		$query = $this->db->query($sql);
		if ($query->num_rows()){
			$sql = "alter table `cms_page` drop column `description`";
			$query = $this->db->query($sql);
		}
		
		$sql = "show columns from `cms_page` like 'type'";
		$query = $this->db->query($sql);
		if ($query->num_rows()){
			$sql = "alter table `cms_page` drop column `type`";
			$query = $this->db->query($sql);
		}
		
		$sql = "show columns from `cms_page_panel_param` like 'language'";
		$query = $this->db->query($sql);
		if (!$query->num_rows()){
			
			$sql = "alter table `cms_page_panel_param` add `language` varchar(10) ".
					"character set ascii collate ascii_bin not null default '' after `cms_page_panel_id`";
			$query = $this->db->query($sql);
			
			$sql = "ALTER TABLE cms_page_panel_param DROP INDEX cms_page_panel_idx";
			$query = $this->db->query($sql);
			
			$sql = "ALTER TABLE `cms_page_panel_param` ADD UNIQUE KEY `cms_page_panel_idx` (`cms_page_panel_id`,`language`,`name`)";
			$query = $this->db->query($sql);
			
		}

		file_put_contents($flag, date('c'));

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
	
	function _stamp_page_audit_meta(&$meta, $is_create = false) {

		$cms_user_id = !empty($_SESSION['cms_user']['cms_user_id']) ? (int)$_SESSION['cms_user']['cms_user_id'] : 0;
		$now = (new \DateTime())->getTimestamp();

		if ($is_create) {
			$meta['create_cms_user_id'] = $cms_user_id;
			$meta['create_time'] = $now;
		}

		$meta['update_cms_user_id'] = $cms_user_id;
		$meta['update_time'] = $now;

	}

	function update_page($cms_page_id, $data, $language = false, $audit_create = false){
		
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

		$this->_stamp_page_audit_meta($meta, $audit_create);
		
		$data['meta'] = json_encode($meta);

		$sql = "update cms_page set ".implode(' = ? , ', array_keys($data))." = ? where cms_page_id = '".(int)$cms_page_id."' ";
		$this->db->query($sql, $data);
		
		return $cms_page_id;
		
	}
	
	function create_page($data){

		$sql = "insert into cms_page set slug = '', sort = 0, meta = '', position = '' ";
		$this->db->query($sql);
		$cms_page_id = $this->db->insert_id();

		$this->update_page($cms_page_id, $data, false, true);

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
	
	function update_page_visibility($cms_page_id){
		
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$data = $this->get_page($cms_page_id);
		
		if (empty($data['cms_page_id'])){
			return false;
		}

		$position = !empty($data['position']) ? $data['position'] : 'main';
		if ($position !== 'main'){
			return false;
		}

		$page_class = $this->get_page_class($data);

		// List template shells: fixed namespaced slug, never public
		if ($page_class === 'list'){
			$slug = !empty($data['list_panel'])
				? $this->list_template_slug_from_panel($data['list_panel'])
				: (string)($data['slug'] ?? '');
			if ($slug === ''){
				return false;
			}
			$this->cms_slug_model->delete_slug($cms_page_id);
			$this->cms_slug_model->set_page_slug($cms_page_id, $slug, 1);
			$this->update_page($cms_page_id, ['slug' => $slug]);
			return $slug;
		}

		// System pages: fixed non-numeric slug (not-found / internal-error / timeout)
		if ($page_class === 'system'){
			$slug = (string)($data['slug'] ?? '');
			if ($slug === ''){
				return false;
			}
			$this->cms_slug_model->delete_slug($cms_page_id);
			// status 0 = in routes; empty content is still a valid shell
			$this->cms_slug_model->set_page_slug($cms_page_id, $slug, empty($data['status']) ? 0 : 1);
			$this->update_page($cms_page_id, ['slug' => $slug]);
			return $slug;
		}

		// User pages: normal slugify + automatic/hidden
		$slug = $this->cms_slug_model->generate_page_slug($cms_page_id, $data['slug']);

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by(['cms_page_id' => $cms_page_id, 'show' => 1, ]);
		$number_panels = count($panels);

		if (empty($data['status']) && $number_panels > 0){
			$this->cms_slug_model->set_page_slug($cms_page_id, $slug, 0);
		} else {
			$this->cms_slug_model->set_page_slug($cms_page_id, $slug, 1);
		}

		$this->update_page($cms_page_id, ['slug' => $slug]);

		return $slug;

	}

	/**
	 * Admin grouping: user | list | system (meta.page_class; default user).
	 */
	function get_page_class($page){

		if (!is_array($page)){
			return 'user';
		}

		$class = $page['page_class'] ?? '';
		if ($class === 'list' || $class === 'system'){
			return $class;
		}

		return 'user';

	}

	/**
	 * shop/product → shop_product ; shop/product_item → shop_product-item
	 */
	function list_template_slug($module, $panel){

		$module = trim((string)$module);
		$panel = trim((string)$panel);
		if ($module === '' || $panel === ''){
			return '';
		}

		return $module.'_'.str_replace('_', '-', $panel);

	}

	function list_template_slug_from_panel($panel_name){

		$panel_name = trim((string)$panel_name);
		if ($panel_name === '' || !stristr($panel_name, '/')){
			return '';
		}

		list($module, $panel) = explode('/', $panel_name, 2);
		return $this->list_template_slug($module, $panel);

	}

	/**
	 * Reverse list_template_slug using known module ids (longest prefix first).
	 * @return string module/panel or ''
	 */
	function list_template_panel_from_slug($slug){

		$slug = trim((string)$slug);
		if ($slug === '' || !stristr($slug, '_')){
			return '';
		}

		$modules = $GLOBALS['config']['modules'] ?? [];
		if (!is_array($modules)){
			return '';
		}

		usort($modules, function($a, $b){
			return strlen($b) - strlen($a);
		});

		foreach ($modules as $module){
			$prefix = $module.'_';
			if (strpos($slug, $prefix) !== 0){
				continue;
			}
			$rest = substr($slug, strlen($prefix));
			if ($rest === ''){
				continue;
			}
			$panel = str_replace('-', '_', $rest);
			return $module.'/'.$panel;
		}

		return '';

	}

	/**
	 * Linkable list panel types: list + truthy link_target.
	 * @return array list of [panel_name, module, panel, title, slug]
	 */
	function get_linkable_list_types(){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_module_model');

		$return = [];

		foreach ($GLOBALS['config']['modules'] as $module){
			foreach (glob($GLOBALS['config']['base_path'].'modules/'.$module.'/definitions/*.json') as $filename){
				$list_name = basename($filename, '.json');
				$panel_name = $module.'/'.$list_name;
				$config = $this->cms_panel_model->get_cms_panel_config($panel_name);
				if (empty($config['list'])){
					continue;
				}
				$link_target = $config['list']['link_target'] ?? null;
				if ($link_target === null || $link_target === '' || $link_target === '0' || $link_target === 0){
					continue;
				}

				$mod_config = $this->cms_module_model->get_module_config($module);
				$module_label = !empty($mod_config['name']) ? $mod_config['name'] : ucfirst($module);
				$panel_label = $config['label'] ?? ($config['list']['item_title'] ?? ucfirst(str_replace('_', ' ', $list_name)));

				$return[] = [
					'panel_name' => $panel_name,
					'module' => $module,
					'panel' => $list_name,
					'title' => $module_label.' - '.$panel_label,
					'slug' => $this->list_template_slug($module, $list_name),
				];
			}
		}

		return $return;

	}

	function get_system_page_defs(){

		// Non-numeric slugs only (numeric strings collide with cms_page_id routing)
		return [
			['slug' => 'not-found', 'title' => '404 - Not found', 'error' => '404', ],
			['slug' => 'internal-error', 'title' => '500 - Internal error', 'error' => '500', ],
			['slug' => 'timeout', 'title' => 'Timeout', 'error' => 'timeout', ],
		];

	}

	/**
	 * System page def for error key: 404 | 500 | timeout
	 */
	function get_system_page_def_for_error($error_key){

		$error_key = (string)$error_key;
		foreach ($this->get_system_page_defs() as $def){
			if (($def['error'] ?? '') === $error_key || ($def['slug'] ?? '') === $error_key){
				return $def;
			}
		}
		return null;

	}

	/**
	 * Create missing list template + system main pages (page_class meta). Idempotent.
	 */
	function ensure_special_pages(){

		$this->_ensure_cms_page_schema();
		$this->ensure_list_template_pages();
		$this->ensure_system_pages();

	}

	function ensure_list_template_pages(){

		$pages = $this->get_cms_pages();
		$by_list_panel = [];
		$by_slug = [];
		foreach ($pages as $page){
			if (!empty($page['list_panel'])){
				$by_list_panel[$page['list_panel']] = $page;
			}
			if (!empty($page['slug'])){
				$by_slug[$page['slug']] = $page;
			}
		}

		foreach ($this->get_linkable_list_types() as $type){
			$existing = $by_list_panel[$type['panel_name']] ?? ($by_slug[$type['slug']] ?? null);
			if (!empty($existing['cms_page_id'])){
				// Keep class/meta/slug aligned if partial
				$need = [];
				if (($existing['page_class'] ?? '') !== 'list'){
					$need['page_class'] = 'list';
				}
				if (($existing['list_panel'] ?? '') !== $type['panel_name']){
					$need['list_panel'] = $type['panel_name'];
				}
				if (($existing['slug'] ?? '') !== $type['slug']){
					$need['slug'] = $type['slug'];
				}
				if (($existing['title'] ?? '') === '' || ($existing['title'] ?? '') === ($existing['slug'] ?? '')){
					$need['title'] = $type['title'];
				}
				if ($need){
					$need['position'] = 'main';
					$this->update_page($existing['cms_page_id'], $need);
					$this->update_page_visibility($existing['cms_page_id']);
				}
				continue;
			}

			$cms_page_id = $this->create_page([
				'position' => 'main',
				'sort' => 9000 + count($by_slug),
				'slug' => $type['slug'],
				'title' => $type['title'],
				'page_class' => 'list',
				'list_panel' => $type['panel_name'],
				'status' => 0,
				'description' => '',
				'image' => '',
			]);
			$this->update_page_visibility($cms_page_id);
			$by_slug[$type['slug']] = ['cms_page_id' => $cms_page_id];
			$by_list_panel[$type['panel_name']] = ['cms_page_id' => $cms_page_id];
		}

	}

	function ensure_system_pages(){

		$pages = $this->get_cms_pages();
		$by_slug = [];
		foreach ($pages as $page){
			if (!empty($page['slug'])){
				$by_slug[$page['slug']] = $page;
			}
		}

		foreach ($this->get_system_page_defs() as $i => $def){
			$existing = $by_slug[$def['slug']] ?? null;
			if (!empty($existing['cms_page_id'])){
				$need = [];
				if (($existing['page_class'] ?? '') !== 'system'){
					$need['page_class'] = 'system';
				}
				if (empty($existing['title']) || $existing['title'] === $existing['slug']){
					$need['title'] = $def['title'];
				}
				if ($need){
					$need['position'] = 'main';
					$this->update_page($existing['cms_page_id'], $need);
					$this->update_page_visibility($existing['cms_page_id']);
				}
				continue;
			}

			$cms_page_id = $this->create_page([
				'position' => 'main',
				'sort' => 9500 + $i,
				'slug' => $def['slug'],
				'title' => $def['title'],
				'page_class' => 'system',
				'status' => 0,
				'description' => '',
				'image' => '',
			]);
			$this->update_page_visibility($cms_page_id);
			$by_slug[$def['slug']] = ['cms_page_id' => $cms_page_id];
		}

	}

	/**
	 * Whether this main page is a list-type template shell (hidden public slug).
	 */
	function is_list_template_page($page){

		if (!is_array($page)){
			return false;
		}

		if ($this->get_page_class($page) === 'list'){
			return true;
		}

		$slug = $page['slug'] ?? '';
		if ($slug === ''){
			return false;
		}

		$panel = $this->list_template_panel_from_slug($slug);
		if ($panel === ''){
			return false;
		}

		foreach ($this->get_linkable_list_types() as $type){
			if ($type['panel_name'] === $panel){
				return true;
			}
		}

		return false;

	}

}
