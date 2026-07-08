<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_languages_local_label extends CI_Controller {

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
					'col' => 'local_label',
					'value' => $params['value'] ?? '',
					'cms_language' => $params['cms_language'] ?? '',
			]);

		}

		return $params;

	}

	function panel_params($params){

		if (isset($params['value'])){
			return $params;
		}

		if (!empty($params['ds']) && !empty($params['base_id'])){

			$this->load->model('cms/cms_page_panel_model');
			$rows = $this->run_panel_method('cms/cms_languages', 'ds_languages', [
					'do' => 'L',
					'id' => $params['base_id'],
			]);

			$params['value'] = '';

			if (is_array($rows)){
				foreach ($rows as $row){
					if (isset($row['id']) && (string)$row['id'] === (string)$params['item_id'] && array_key_exists('local_label', $row)){
						$params['value'] = $row['local_label'];
						break;
					}
				}
			}

		}

		return $params;

	}

}