<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class collection extends \Controller {

	/**
	 * Admin save: fill hash from heading before write.
	 */
	function on_update($params){

		$heading = trim((string)($params['heading'] ?? $params['title'] ?? ''));
		$id = (int)($params['cms_page_panel_id'] ?? 0);
		$params['hash'] = $this->ensure_collection_hash_value($heading, $id);

		return $params;

	}

	/**
	 * Unique hash among shop/collection panels (not cms_slug).
	 * $exclude_id = this panel when updating.
	 */
	function ensure_collection_hash_value($heading, $exclude_id = 0){

		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_page_panel_model');

		$base = $this->cms_slug_model->_slugify_candidate($heading);
		if ($base === ''){
			$base = 'collection';
		}

		$exclude_id = (int)$exclude_id;
		$used = $this->_existing_collection_hashes($exclude_id);

		$candidate = $base;
		$i = 1;
		while (isset($used[$candidate])){
			$candidate = $base.'-'.$i;
			$i++;
		}

		return $candidate;

	}

	/**
	 * Set hash on panel if empty (or force regenerate).
	 * Callable as ensure_collection_hash($id, $heading, $force)
	 * or via run_panel_method with array params (returns same array + hash).
	 */
	function ensure_collection_hash($cms_page_panel_id, $heading = '', $force = false){

		$return_array = false;
		if (is_array($cms_page_panel_id)){
			$return_array = true;
			$params = $cms_page_panel_id;
			$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
			$heading = $params['heading'] ?? '';
			$force = !empty($params['force']);
		} else {
			$params = [];
		}

		$cms_page_panel_id = (int)$cms_page_panel_id;
		if ($cms_page_panel_id < 1){
			return $return_array ? $params : '';
		}

		$this->load->model('cms/cms_page_panel_model');

		$panel = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
		if (empty($panel['cms_page_panel_id'])){
			return $return_array ? $params : '';
		}

		$existing = trim((string)($panel['hash'] ?? ''));
		if ($existing !== '' && !$force){
			if ($return_array){
				$params['hash'] = $existing;
				return $params;
			}
			return $existing;
		}

		$heading = trim((string)$heading);
		if ($heading === ''){
			$heading = trim((string)($panel['heading'] ?? $panel['title'] ?? ''));
		}

		$hash = $this->ensure_collection_hash_value($heading, $cms_page_panel_id);
		$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, [
				'hash' => $hash,
		], false);

		if ($return_array){
			$params['hash'] = $hash;
			return $params;
		}

		return $hash;

	}

	function _existing_collection_hashes($exclude_id = 0){

		$this->load->model('cms/cms_page_panel_model');

		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
				'panel_name' => 'shop/collection',
		]);
		if (!is_array($panels)){
			return [];
		}

		$used = [];
		$exclude_id = (int)$exclude_id;
		foreach ($panels as $row){
			$id = (int)($row['cms_page_panel_id'] ?? 0);
			if ($id < 1 || ($exclude_id > 0 && $id === $exclude_id)){
				continue;
			}
			$h = trim((string)($row['hash'] ?? ''));
			if ($h !== ''){
				$used[$h] = true;
			}
		}

		return $used;

	}

}
