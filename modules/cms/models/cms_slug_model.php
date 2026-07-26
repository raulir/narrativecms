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

		// Drop repeated words (first occurrence kept) — e.g. birthday … birthday → one birthday
		$parts = preg_split('/\s+/', trim($slug_string));
		$seen = array();
		$unique = array();
		if (is_array($parts)){
			foreach ($parts as $part){
				if ($part === ''){
					continue;
				}
				if (isset($seen[$part])){
					continue;
				}
				$seen[$part] = true;
				$unique[] = $part;
			}
		}
		$slug_string = implode(' ', $unique);

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

		$this->invalidate_sitemap_cache();

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

		$this->invalidate_sitemap_cache();

	}

	/**
	 * Max age for file + HTTP cache of sitemap (seconds). Cheap rebuild = short TTL ok.
	 */
	function sitemap_cache_ttl(){

		return 300;

	}

	function sitemap_cache_path(){

		return $GLOBALS['config']['base_path'].'cache/sitemap.xml';

	}

	function robots_txt_path(){

		return $GLOBALS['config']['base_path'].'robots.txt';

	}

	/**
	 * Absolute site prefix ending with / (scheme+host+base_url).
	 * Shared by sitemap <loc> and robots Sitemap: line.
	 */
	function public_site_prefix(){

		$host = !empty($GLOBALS['config']['base_host'])
			? rtrim($GLOBALS['config']['base_host'], '/')
			: (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443))
				? 'https://' : 'http://').($_SERVER['HTTP_HOST'] ?? 'localhost');

		$base = $GLOBALS['config']['base_url'] ?? '/';
		if ($base === '/' || $base === ''){
			return $host.'/';
		}

		return $host.'/'.trim($base, '/').'/';

	}

	/**
	 * Absolute crawler URL for the pretty sitemap path.
	 */
	function sitemap_public_url(){

		return $this->public_site_prefix().'sitemap.xml';

	}

	/**
	 * Drop file cache only — does not build XML.
	 * Rebuild runs solely from cms/sitemap API (get_sitemap_xml_cached).
	 */
	function invalidate_sitemap_cache(){

		$path = $this->sitemap_cache_path();
		if (is_file($path)){
			@unlink($path);
		}

	}

	/**
	 * Build sitemap XML string from visible cms_route rows.
	 * Call only from get_sitemap_xml_cached / cms/sitemap API.
	 */
	function build_sitemap_xml(){

		list($table, $slug_col) = $this->_route_table();

		$sql = 'select `'.$slug_col.'` as slug from `'.$table.'` where status = 0 and `'.$slug_col.'` != \'\' order by `'.$slug_col.'`';
		$query = $this->db->query($sql);
		$routes = $query ? $query->result_array() : [];

		$prefix = $this->public_site_prefix();

		$lines = [];
		$lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

		foreach ($routes as $route){
			$slug = trim((string)($route['slug'] ?? ''), '/');
			if ($slug === ''){
				continue;
			}
			$loc = htmlspecialchars($prefix.$slug.'/', ENT_XML1 | ENT_QUOTES, 'UTF-8');
			$lines[] = '<url><loc>'.$loc.'</loc></url>';
		}

		$lines[] = '</urlset>';

		return implode("\n", $lines)."\n";

	}

	/**
	 * Default robots.txt body (Allow all + Sitemap for this host).
	 */
	function build_robots_txt(){

		$lines = [];
		$lines[] = '# Managed by CMS — Sitemap URL is checked/updated when the sitemap cache rebuilds';
		$lines[] = 'User-agent: *';
		$lines[] = 'Allow: /';
		$lines[] = '';
		$lines[] = 'Sitemap: '.$this->sitemap_public_url();

		return implode("\n", $lines)."\n";

	}

	/**
	 * Ensure project-root robots.txt exists and points Sitemap: at this site’s /sitemap.xml.
	 * Preserves custom User-agent / Allow / Disallow rules; only fixes Sitemap line(s).
	 * Called when the sitemap file cache is rebuilt (same host context as <loc> URLs).
	 */
	function ensure_robots_txt(){

		$path = $this->robots_txt_path();
		$sitemap_line = 'Sitemap: '.$this->sitemap_public_url();

		if (!is_file($path) || !is_readable($path) || (int)@filesize($path) === 0){
			@file_put_contents($path, $this->build_robots_txt());
			return;
		}

		$content = @file_get_contents($path);
		if ($content === false){
			@file_put_contents($path, $this->build_robots_txt());
			return;
		}

		// Already correct: exactly one Sitemap line matching this host
		if (preg_match_all('/^Sitemap:\s*(\S+)\s*$/mi', $content, $matches)){
			if (count($matches[0]) === 1 && trim($matches[0][0]) === $sitemap_line){
				return;
			}
		} else {
			// No Sitemap directive — append
			$body = rtrim($content)."\n\n".$sitemap_line."\n";
			@file_put_contents($path, $body);
			return;
		}

		// Wrong or multiple Sitemap lines: strip all, append the correct one
		$body = preg_replace('/^Sitemap:\s*\S+\s*$/mi', '', $content);
		$body = preg_replace("/\n{3,}/", "\n\n", $body);
		$body = rtrim($body)."\n\n".$sitemap_line."\n";
		@file_put_contents($path, $body);

	}

	/**
	 * Cached sitemap XML: file until invalidate or TTL (default 300s).
	 * On rebuild, also ensure robots.txt Sitemap: matches this environment.
	 */
	function get_sitemap_xml_cached(){

		$path = $this->sitemap_cache_path();
		$ttl = $this->sitemap_cache_ttl();

		if (is_file($path)){
			$age = time() - (int)filemtime($path);
			if ($age >= 0 && $age < $ttl){
				$cached = @file_get_contents($path);
				if ($cached !== false && $cached !== ''){
					return $cached;
				}
			}
		}

		$xml = $this->build_sitemap_xml();
		@file_put_contents($path, $xml);
		$this->ensure_robots_txt();

		return $xml;

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

		$this->invalidate_sitemap_cache();

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

	/**
	 * Dump one table to cache/db/{table}_YYYYMMDD_HHMMSS.zip (SQL inside).
	 * No _bu tables — DB backups live under cache/db/ as zip (project rule).
	 *
	 * @return string|false relative path from base_path, or false on failure
	 */
	function backup_table_sql_zip($table){

		$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
		if ($table === '' || !$this->db->table_exists($table)){
			return false;
		}

		$dir = $GLOBALS['config']['base_path'].'cache/db/';
		if (!is_dir($dir)){
			if (!@mkdir($dir, 0755, true) && !is_dir($dir)){
				return false;
			}
		}

		$stamp = date('Ymd_His');
		$sql_name = $table.'_'.$stamp.'.sql';
		$sql_path = $dir.$sql_name;
		$zip_name = $table.'_'.$stamp.'.zip';
		$zip_path = $dir.$zip_name;

		if (is_file($sql_path)){
			@unlink($sql_path);
		}
		if (is_file($zip_path)){
			@unlink($zip_path);
		}

		include_once $GLOBALS['config']['base_path'].'system/vendor/mysqldump/mysqldump.php';

		$db = $GLOBALS['config']['database'];
		Export_Database(
			$db['hostname'],
			$db['username'],
			$db['password'],
			$db['database'],
			array($table),
			$sql_path
		);

		if (!is_file($sql_path) || (int)@filesize($sql_path) < 1){
			@unlink($sql_path);
			return false;
		}

		$zip = new \ZipArchive();
		if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true){
			@unlink($sql_path);
			return false;
		}
		$zip->addFile($sql_path, $sql_name);
		$zip->close();
		@unlink($sql_path);

		if (!is_file($zip_path)){
			return false;
		}

		return 'cache/db/'.$zip_name;

	}

	/**
	 * Full rebuild of cms_route from current pages + link_target list items.
	 * Backs up current table to cache/db/*.zip first (no _bu tables).
	 *
	 * @return array{ok:int,backup?:string,pages:int,list_items:int,error?:string}
	 */
	function rebuild_all_routes(){

		list($table, $slug_col) = $this->_route_table();

		if (!$this->db->table_exists($table)){
			return array('ok' => 0, 'error' => 'Table '.$table.' missing', 'pages' => 0, 'list_items' => 0);
		}

		$backup = $this->backup_table_sql_zip($table);
		if ($backup === false){
			return array('ok' => 0, 'error' => 'Failed to write backup under cache/db/', 'pages' => 0, 'list_items' => 0);
		}

		$this->db->query('TRUNCATE TABLE `'.$table.'`');

		$stats = array(
			'ok' => 1,
			'backup' => $backup,
			'pages' => 0,
			'list_items' => 0,
		);

		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_page_panel_model');

		// Main CMS pages (user / system / list shells)
		$pages = $this->cms_page_model->get_cms_pages();
		foreach ($pages as $page){
			$position = !empty($page['position']) ? $page['position'] : 'main';
			if ($position !== 'main'){
				continue;
			}
			$cms_page_id = (int)($page['cms_page_id'] ?? 0);
			if ($cms_page_id < 1){
				continue;
			}
			$result = $this->cms_page_model->update_page_visibility($cms_page_id);
			if ($result !== false){
				$stats['pages']++;
			}
		}

		// List items with public slugs (link_target)
		$types = $this->cms_page_model->get_linkable_list_types();
		foreach ($types as $type){
			$panel_name = $type['panel_name'] ?? '';
			if ($panel_name === ''){
				continue;
			}

			// All rows of this list type (including hidden)
			$items = $this->cms_page_panel_model->get_cms_page_panels_by(array(
				'panel_name' => $panel_name,
			));
			if (!is_array($items)){
				continue;
			}

			foreach ($items as $item){
				$id = (int)($item['cms_page_panel_id'] ?? 0);
				if ($id < 1){
					continue;
				}

				$target = $panel_name.'='.$id;

				$title = $this->cms_page_panel_model->get_list_item_title($item);
				if ($title === '' || $title === false || $title === null){
					if (!empty($item['title'])){
						$title = $item['title'];
					} else if (!empty($item['heading'])){
						$title = $item['heading'];
					} else {
						$title = $panel_name.' '.$id;
					}
				}

				// slugify_slug after truncate — uniqueness among rebuild set
				$slug = $this->slugify_slug($title);
				// status 0 = visible (show), 1 = hidden — same as admin list save
				$status = empty($item['show']) ? 1 : 0;

				$sql = 'insert into `'.$table.'` set `'.$slug_col.'` = ? , target = ? , status = ? ';
				$this->db->query($sql, array($slug, $target, $status));

				$stats['list_items']++;
			}
		}

		$this->invalidate_sitemap_cache();

		return $stats;

	}

}
