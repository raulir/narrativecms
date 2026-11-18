<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Gears menu → Versions (cms_versions.md). The toolbar adds it only while page panel versions are on.
 */
class cms_page_panel_button_versions extends \Controller {

	function panel_params($params){

		add_css('modules/cms/css/cms_popup.scss');
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_popup.js';

		return $params;

	}

}
