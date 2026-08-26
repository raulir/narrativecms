<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_user extends \Controller {

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'admin_logout'){
			cms_session_clear_cms_admin();
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		if ($do == 'admin_logout_soft'){
			cms_session_clear_cms_admin();
		}

		return $params;

	}

	function panel_params($params){

		return array();

	}

}
