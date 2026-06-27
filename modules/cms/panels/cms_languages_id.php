<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_languages_id extends CI_Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_languages.scss');

	}

	function panel_action($params){

		if (!empty($params['do']) && $params['do'] == 'update_field' && !empty($params['base_id'])){

			$this->load->model('cms/cms_page_panel_model');
			$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);

			$this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], [
					'do' => 'U',
					'id' => $params['base_id'],
					'row_id' => $params['item_id'],
					'col' => 'language_id',
					'value' => $params['value'] ?? '',
			]);

		}

		return $params;

	}

	function panel_params($params){

		if (!isset($params['value'])){
			$params['value'] = '';
		}

		$params['readonly'] = ($params['value'] !== '' && $params['value'] !== null) ? 1 : 0;

		return $params;

	}

}