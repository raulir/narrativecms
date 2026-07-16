<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_language_select extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
		
		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_page_panel_toolbar.scss');
		
	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do === 'cms_language_set'){

			$cms_language = $this->input->post('language');
			$this->load->model('cms/cms_language_model');
			$resolved_language = $this->cms_language_model->resolve_language_id(
					$cms_language,
					$GLOBALS['language']['languages'] ?? []
			);
			$_SESSION['cms_language'] = $resolved_language !== false ? $resolved_language : $cms_language;

		}

		return $params;

	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$this->load->model('cms/cms_language_model');

		$params['selected'] = $this->cms_page_panel_model->get_cms_language();
		$params['default_language'] = $this->cms_language_model->get_default();
		
		return $params;
		
	}

}
