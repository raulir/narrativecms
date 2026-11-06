<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_import extends \Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $params['cms_do'] ?? '';
		// Request values, taken before the reply keys below are reset
		$request = [
			'list_panel' => (string)($params['list_panel'] ?? ''),
			'token' => (string)($params['token'] ?? ''),
			'zip_id' => $params['zip_id'] ?? '',
			'mode' => (string)($params['mode'] ?? ''),
			'fk_ids' => $params['fk_ids'] ?? [],
		];
		$params['view'] = 'upload';
		$params['message'] = '';
		$params['token'] = '';
		$params['blocks'] = [];
		$params['list_label'] = '';

		if ($do === 'cms_page_panel_import_read' || $do === 'cms_page_panel_import_apply' || $do === 'cms_page_panel_import_discard'){
			$this->load->model('cms/cms_page_panel_import_model');
		}

		if ($do === 'cms_page_panel_import_read'){
			$params['list_label'] = trim($request['list_panel']);
			return $this->cms_page_panel_import_model->read_upload($params);
		}

		if ($do === 'cms_page_panel_import_apply'){
			return $this->cms_page_panel_import_model->apply($request);
		}

		if ($do === 'cms_page_panel_import_discard'){
			return $this->cms_page_panel_import_model->discard($request);
		}

		return $params;

	}

}
