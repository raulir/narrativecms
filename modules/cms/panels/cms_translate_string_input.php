<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_translate_string_input extends CI_Controller {

	function panel_params($params){

		$params['field_type'] = $params['field_type'] ?? 'text';
		$params['value'] = $params['value'] ?? '';
		$params['language_id'] = $params['language_id'] ?? '';

		return $params;

	}

}