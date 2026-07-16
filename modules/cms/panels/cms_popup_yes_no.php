<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

class cms_popup_yes_no extends \Controller {

	function panel_params($params){

		add_css('modules/cms/css/cms_popup_yes_no.scss');

		return $params;

	}

}