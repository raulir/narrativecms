<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

class cms_page_panel_button_data_purge extends \Controller {

	function panel_params($params){

		add_css('modules/cms/css/cms_popup.scss');
		add_css('modules/cms/css/cms_page_panel_data_purge.scss');
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_popup.js';
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_page_panel_button_data_purge.js';

		return $params;

	}

}
