<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_page_cache_model extends Model {

	function invalidate_page($cms_page_id) {

		$this->load->library('cache');
		$this->cache->invalidate_page($cms_page_id);

	}

	function invalidate_list_item($panel_name, $cms_page_panel_id) {

		$this->load->library('cache');
		$this->cache->invalidate_list_item($panel_name, $cms_page_panel_id);

	}

	function invalidate_slug($slug) {

		$this->load->library('cache');
		$this->cache->invalidate_slug($slug);

	}

}