<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_translate_string_operations extends CI_Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do !== 'cms_translate_string_save'){
			return $params;
		}

		$this->load->model('cms/cms_page_panel_model');

		$cms_page_panel_id = (int)$this->input->post('cms_page_panel_id');
		$field_name = $this->input->post('field_name');
		$values = $this->input->post('values');
		$cms_language = $this->input->post('cms_language');

		if (is_string($values)){
			$values = cms_json_decode($values, 'translate_string_values');
		}

		$result = $this->cms_page_panel_model->save_translate_string($cms_page_panel_id, $field_name, $values, $cms_language);

		print(json_encode(['result' => $result], JSON_PRETTY_PRINT));
		die();

	}

	function panel_params($params){

		return $params;

	}

}