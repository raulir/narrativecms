<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

class cms_popup_shell extends \Controller {

	function panel_params($params){

		add_css('modules/cms/css/cms_popup.scss');
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_popup.js';

		return $params;

	}

}