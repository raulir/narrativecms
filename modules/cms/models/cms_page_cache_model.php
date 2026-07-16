<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_cache_model extends \Model {

	function _cache() {

		$CI =& get_instance();
		if (empty($CI->cache)) {
			$CI->load->library('cache');
		}
		return $CI->cache;

	}

	function invalidate_page($cms_page_id) {

		$this->_cache()->invalidate_page($cms_page_id);

	}

	function invalidate_list_item($panel_name, $cms_page_panel_id) {

		$this->_cache()->invalidate_list_item($panel_name, $cms_page_panel_id);

	}

	function invalidate_slug($slug) {

		$this->_cache()->invalidate_slug($slug);

	}

}