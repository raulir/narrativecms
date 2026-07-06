<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_translate_string extends CI_Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_input_grid.scss');
		add_css('modules/cms/css/cms_translate_string.scss');

		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_translate_string.js';

	}

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');

		$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		$field_name = $params['field_name'] ?? '';
		$field_type = $params['field_type'] ?? '';

		$data = $this->cms_page_panel_model->get_translate_string_data($cms_page_panel_id, $field_name, $field_type);

		if (!empty($data['error'])){
			$params['error'] = $data['error'];
			return $params;
		}

		$params['field_name'] = $data['field_name'];
		$params['field_path'] = $data['field_path'];
		$params['field_type'] = $data['field_type'];
		$params['definition_default'] = $data['definition_default'];
		$params['default_language'] = $data['default_language'];
		$params['main_value'] = $data['main_value'];
		$params['other_rows'] = $data['other_rows'];
		$params['cms_page_panel_id'] = $cms_page_panel_id;

		return $params;

	}

}