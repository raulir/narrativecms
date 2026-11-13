<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Access denied popup (cms_site_main.js). The login link comes from the access model, never from the request.
 */
class cms_access_denied extends \Controller {

	function panel_params($params){

		$this->load->model('cms/cms_access_model');
		$login = $this->cms_access_model->get_login_redirect();

		$params['login_url'] = $login['url'];
		$params['login_text'] = $login['text'];

		return $params;

	}

}
