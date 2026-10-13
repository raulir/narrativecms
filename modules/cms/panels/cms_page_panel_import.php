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

		$do = $this->input->post('do');
		$params['view'] = 'upload';
		$params['message'] = '';
		$params['token'] = '';
		$params['blocks'] = [];
		$params['list_label'] = '';

		if ($do === 'cms_page_panel_import_read' || $do === 'cms_page_panel_import_apply' || $do === 'cms_page_panel_import_discard'){
			$this->load->model('cms/cms_page_panel_import_model');
		}

		if ($do === 'cms_page_panel_import_read'){
			return $this->cms_page_panel_import_model->read_upload($params);
		}

		if ($do === 'cms_page_panel_import_apply'){
			return $this->cms_page_panel_import_model->apply();
		}

		if ($do === 'cms_page_panel_import_discard'){
			return $this->cms_page_panel_import_model->discard();
		}

		return $params;

	}

}
