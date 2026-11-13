<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * One repeater row. cms_input_repeater renders the stored rows with its prepared fields. A row added with
 * "Add element" (ajax) names the panel, definition section and repeater; its fields come from that definition,
 * never from the request.
 */
class cms_input_repeater_item extends \Controller {

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

		if (is_array($params['fields'] ?? null)){
			return $params;
		}

		$repeater = cms_repeater_definition($params['block_panel'] ?? '', $params['section'] ?? '', $params['name'] ?? '');

		if (!$repeater){
			$params['row_error'] = 'No such repeater in the panel definition';
			return $params;
		}

		$base_id = (int)($params['base_id'] ?? 0);

		$params['name'] = $repeater['name'];
		$params['fields'] = cms_repeater_row_fields($repeater['fields'] ?? [], $base_id > 0 ? $base_id : '');
		$params['repeater_data'] = [];
		$params['repeater_key'] = max(0, (int)($params['repeater_key'] ?? 0));
		$params['height'] = !empty($repeater['height']) ? $repeater['height'] : 0;
		$params['readonly'] = !empty($repeater['readonly']) ? 1 : 0;

		return $params;

	}

}
