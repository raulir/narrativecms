<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class searchajax extends CI_Controller{

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_search_model');

		// Frontend search settings (sources, cache, min chars)
		$search_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('search/search');
		if (!is_array($search_settings)){
			$search_settings = [];
		}

		// searchajax settings: no_result / no_characters
		$ajax_settings = $this->cms_page_panel_model->get_cms_page_panel_settings('search/searchajax');
		if (is_array($ajax_settings)){
			$params = array_merge($ajax_settings, $params);
		}

		$term = trim((string)($params['term'] ?? ''));
		$min_chars = (int)($search_settings['min_chars'] ?? 3);
		if ($min_chars < 1){
			$min_chars = 3;
		}
		$cache_hours = (float)($search_settings['cache_hours'] ?? 6);
		if ($cache_hours < 0){
			$cache_hours = 0;
		}
		$cache_ttl = (int)round($cache_hours * 3600);

		$include_pages = (string)($search_settings['include_pages'] ?? '1') !== '0';
		$include_all_fields = (string)($search_settings['include_all_fields'] ?? '0') === '1';
		$include_lists = $this->_include_lists_set($search_settings['include_lists'] ?? []);

		// Fingerprint settings so admin changes miss old HTML
		$settings_fp = md5(json_encode([
				'p' => $include_pages ? 1 : 0,
				'a' => $include_all_fields ? 1 : 0,
				'l' => array_keys($include_lists),
				'm' => $min_chars,
		]));
		$term_norm = mb_strtolower($term);
		$cache_hash = substr(md5($term_norm.'|'.$settings_fp), 0, 24);
		$cache_dir = $GLOBALS['config']['base_path'].'cache/search/';
		$cache_path = $cache_dir.$cache_hash.'.html';

		if ($cache_ttl > 0 && is_file($cache_path) && (time() - filemtime($cache_path)) < $cache_ttl){
			$params['html_cache'] = (string)file_get_contents($cache_path);
			return $params;
		}

		$params['result'] = [];
		$params['error_message'] = '';

		if ($term === '' || mb_strlen($term) < $min_chars){
			$msg = (string)($params['no_characters'] ?? 'Enter {{x}} characters');
			$params['error_message'] = str_ireplace('{{x}}', (string)$min_chars, $msg);
			$params['html_cache'] = $this->_render_html($params);
			$this->_write_html_cache($cache_dir, $cache_path, $params['html_cache'], $cache_ttl);
			return $params;
		}

		$search_opts = [];
		if ($include_all_fields){
			$search_opts['all_fields'] = 1;
		}

		$result = $this->cms_search_model->get_search($term, $search_opts);
		if (!is_array($result) || empty($result['cms_pages'])){
			$result = ['cms_pages' => []];
		}

		// add more data + filter by include_pages / include_lists
		foreach ($result['cms_pages'] as $page_id => $score){

			$slug = $this->cms_slug_model->get_cms_slug_by_target($page_id);

			if (stristr((string)$page_id, '=')){

				// list item page
				list($panel_name, $cms_page_panel_id) = explode('=', $page_id, 2);

				if (empty($include_lists[$panel_name])){
					continue;
				}

				$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);

				if (!empty($data['show']) && !empty($slug)){

					$params['result'][$page_id] = [
							'image' => $data['image'] ?? '',
							'text' => $data['text'] ?? '',
							'heading' => $data['heading'] ?? '',
							'page_id' => $page_id,
							'slug' => $slug,
							'score' => $score,
							'data' => $data,
					];
				}

			} else {

				if (!$include_pages){
					continue;
				}

				// static page
				$page_data = $this->cms_page_model->get_page($page_id);
				if (!is_array($page_data)){
					$page_data = [];
				}

				$lists = $this->cms_page_panel_model->get_lists();
				if (in_array($slug, $lists, true)){
					$slug = '';
				}

				if (!empty($slug) && empty($page_data['status'])){
					$params['result'][$page_id] = [
							'image' => (!empty($page_data['image']) ? $page_data['image'] : ''),
							'text' => 'page',
							'heading' => (!empty($page_data['title']) ? $page_data['title'] : '[ no title ]'),
							'page_id' => $page_id,
							'slug' => $slug,
							'score' => $score,
							'data' => $page_data,
					];
				}

			}

		}

		uasort($params['result'], function($a, $b){
			if ($a['score'] == $b['score']){
				return 0;
			}
			return ($a['score'] < $b['score']) ? 1 : -1;
		});

		$params['result'] = array_slice($params['result'], 0, 12);

		if (empty($params['result']) && empty($params['error_message'])){
			// template uses no_result when result empty
		}

		$params['html_cache'] = $this->_render_html($params);
		$this->_write_html_cache($cache_dir, $cache_path, $params['html_cache'], $cache_ttl);

		return $params;

	}

	function _include_lists_set($rows){

		$set = [];
		if (!is_array($rows)){
			return $set;
		}
		foreach ($rows as $row){
			$list = trim((string)($row['list'] ?? ''));
			if ($list !== ''){
				$set[$list] = true;
			}
		}
		return $set;

	}

	function _render_html($params){

		// Render searchajax template only (no panel chrome) for injection into .search_results
		$path = $GLOBALS['config']['base_path'].'modules/search/templates/searchajax.tpl.php';
		if (!is_file($path)){
			return '';
		}
		extract($params, EXTR_SKIP);
		ob_start();
		include $path;
		return (string)ob_get_clean();

	}

	function _write_html_cache($cache_dir, $cache_path, $html, $cache_ttl){

		if ($cache_ttl < 1){
			return;
		}
		if (!is_dir($cache_dir)){
			@mkdir($cache_dir, 0755, true);
		}
		@file_put_contents($cache_path, $html);

	}

}
