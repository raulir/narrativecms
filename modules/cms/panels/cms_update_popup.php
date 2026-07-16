<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_update_popup extends \Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		add_css('modules/cms/css/cms_popup.scss');
		add_css('modules/cms/css/cms_update.scss');
		add_css('modules/cms/css/cms_schema.scss');
		add_js('modules/cms/js/cms_schema.js');

		return $params;

	}

}
