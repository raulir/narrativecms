<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Public URL routes (slug → target). Table: cms_route (slug PK, target, status).
 * Class name kept as cms_slug_model for existing loaders; use load->model('cms/cms_slug_model').
 */
class cms_slug_model extends \Model {

	/**
	 * @return array{0:string,1:string} [table, slug_column]
	 */
	function _route_table(){

		return array('cms_route', 'slug');

	}

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
			$target = str_replace('_/', end($GLOBALS['config']['modules']).'/', $target);
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

		list($table, $slug_col) = $this->_route_table();

		$slug = $this->_slugify_candidate($slug_string);

		$final_slug = $slug;
		$i = 1;
		$ok = false;
		while(!$ok){
			$sql = 'select * from `'.$table.'` where `'.$slug_col.'` = ? limit 1';
			$query = $this->db->query($sql, array($final_slug, ));
			if ($query->num_rows()){
				$final_slug = $slug.'-'.$i;
				$i = $i + 1;
			} else {
				$ok = true;
			}
		}

		return $final_slug;

	}

	/**
	 * @param mixed $target page id or list target
	 * @param string $slug
	 * @param int $status 0 = visible, 1 = not visible
	 */
	function set_page_slug($target, $slug, $status){

		if (substr($target, 0, 2) == '_/'){
			$target = str_replace('_/', end($GLOBALS['config']['modules']).'/', $target);
		}

		list($table, $slug_col) = $this->_route_table();

		$this->delete_slug($target);

		$sql = 'insert into `'.$table.'` set `'.$slug_col.'` = ? , target = ? , status = ? ';
		$this->db->query($sql, [$slug, $target, $status, ]);

		$this->_regenerate_sitemap();

		return $slug;

	}

	function delete_slug($target){

		list($table, $slug_col) = $this->_route_table();

		$sql = 'delete from `'.$table.'` where target = ? ';
		$this->db->query($sql, [$target, ]);

		if (stristr($target, '/')){

			list($module, $panel) = explode('/', $target);

			$sql = 'delete from `'.$table.'` where target = ? ';
			$this->db->query($sql, [$panel, ]);

		}

		$this->_regenerate_sitemap();

	}

	/**
	 * creates new sitemap.xml and robots.txt
	 */
	function _regenerate_sitemap(){

		list($table, $slug_col) = $this->_route_table();

		$sql = 'select * from `'.$table.'`';
		$query = $this->db->query($sql);
		$routes = $query->result_array();

		$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
		$protocol = !$secure ? 'http' : 'https';

		$data = array();
		$data[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$data[] = '<urlset xmlns="'.$protocol.'://www.sitemaps.org/schemas/sitemap/0.9">';

		if (!empty($routes )) {
			foreach ($routes as $route) {
				$slug = $route['slug'] ?? '';
				if ($slug && (int)($route['status'] ?? 0) === 0){
					$data[] = '<url><loc>'.$protocol.'://' . $_SERVER['HTTP_HOST'] . '/' . $slug . '/</loc></url>';
				}
			}
		}

		$data[] = '</urlset>';

		$output = implode("\n", $data);
		file_put_contents($GLOBALS['config']['base_path'].'cache/sitemap.xml', $output);

		file_put_contents($GLOBALS['config']['base_path'].'robots.txt', 'Sitemap: '.$protocol.'://'.$_SERVER['HTTP_HOST'].'/cache/sitemap.xml'."\n");

	}

	function get_slug_row_by_target($target){

		$target = trim((string)$target);

		if ($target === ''){
			return false;
		}

		list($table, $slug_col) = $this->_route_table();

		$sql = 'select * from `'.$table.'` where target = ? limit 1';
		$query = $this->db->query($sql, array($target));

		if (!$query->num_rows()){
			return false;
		}

		$row = $query->row_array();
		// Callers that still read cms_slug_id
		if (isset($row['slug'])){
			$row['cms_slug_id'] = $row['slug'];
		}

		return $row;

	}

	function get_cms_slug_by_target($target){

		$row = $this->get_slug_row_by_target($target);

		if ($row === false){
			return '';
		}

		return $row['slug'] ?? '';

	}

	function get_target_by_slug_id($slug_id){

		$slug_id = trim((string)$slug_id);

		if ($slug_id === ''){
			return '';
		}

		list($table, $slug_col) = $this->_route_table();

		$sql = 'select target from `'.$table.'` where `'.$slug_col.'` = ? limit 1';
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

		$current_slug = $row['slug'] ?? '';
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

		list($table, $slug_col) = $this->_route_table();

		$sql = 'update `'.$table.'` set status = ? where target = ? ';
		$this->db->query($sql, [$status, $target, ]);

		$this->_regenerate_sitemap();

	}

	/**
	 * @return string|int full target for pages; list targets return full "panel=id" (fixed)
	 */
	function get_target_by_cms_slug($slug){

		list($table, $slug_col) = $this->_route_table();

		$sql = 'select * from `'.$table.'` where `'.$slug_col.'` = ? and status = 0 limit 1 ';
		$query = $this->db->query($sql, [$slug, ]);
		if ($query->num_rows()){

			$row = $query->row_array();
			// Return full target (Index parses module/panel=id). Legacy stripped after =.
			return $row['target'];

		}

		return 0;

	}

}
