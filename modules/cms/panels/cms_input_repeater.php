<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Repeater input: rows of sub-fields, each row rendered by cms/cms_input_repeater_item (print_fields with
 * the repeater name as prefix). One level only: a repeater inside a repeater row is not rendered.
 */
class cms_input_repeater extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->load->helper('cms/cms_fields_helper');

	}

	function panel_params($params){

		if (!empty($params['prefix'])){
			$params['nested'] = 1;
			return $params;
		}
		$params['nested'] = 0;

		// The rows' field names, the area class and repeater_select targets use the field's own name
		$params['name'] = $params['field_name'] ?? $params['name'];
		$params['data'] = is_array($params['value'] ?? null) ? $params['value'] : [];
		$params['height'] = !empty($params['height']) ? $params['height'] : 0;
		$params['readonly'] = !empty($params['readonly']) ? 1 : 0;
		$params['base_id'] = $params['base_id'] ?? '';

		// Add element asks the server for a row by panel, section and name (cms_input_repeater_item)
		$params['block_panel'] = $params['base_name'] ?? '';
		$params['section'] = $this->_definition_section($params['block_panel'], $params['name'], $params['fields'] ?? null);

		// Row keys only make a row's ids and classes unique: an added row takes the next free one
		$keys = array_filter(array_keys($params['data']), 'is_numeric');
		$params['next_key'] = $keys ? (int)max($keys) + 1 : 0;

		$params['fields'] = cms_repeater_row_fields($params['fields'] ?? [], $params['base_id']);

		cms_repeater_preload_field_js($params['fields']);

		return $params;

	}

	/**
	 * The definition section ("item" or "settings") that has this repeater with these fields; '' when neither.
	 */
	function _definition_section($block_panel, $name, $fields){

		$found = '';

		foreach (['item', 'settings'] as $section){
			$repeater = cms_repeater_definition($block_panel, $section, $name);
			if (!$repeater){
				continue;
			}
			if (($repeater['fields'] ?? null) === $fields){
				return $section;
			}
			if ($found === ''){
				$found = $section;
			}
		}

		return $found;

	}

}
