<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_page_panel_button_targets extends CI_Controller {

	function panel_params($params){

		add_css('modules/cms/css/cms_popup.scss');
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_popup.js';

		return $params;

	}

}