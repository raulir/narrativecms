<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Admin dump tool: rebuild cms_route from pages + link_target list items.
 */
class cms_rebuild_routes extends \Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params = []){

		if (!is_array($params)){
			$params = [];
		}

		$do = '';
		if (is_object($this->input) && method_exists($this->input, 'post')){
			$do = $this->input->post('do');
		}
		if ($do === null || $do === false || $do === ''){
			$do = $params['do'] ?? ($_POST['do'] ?? '');
		}

		if ($do === 'rebuild_routes'){

			@set_time_limit(300);
			if (session_status() === PHP_SESSION_ACTIVE){
				session_write_close();
			}

			$this->load->model('cms/cms_slug_model');
			$params['result'] = $this->cms_slug_model->rebuild_all_routes();

		}

		return $params;

	}

	function panel_params($params){

		if (!isset($params['label'])){
			$params['label'] = 'Public URL routes';
		}

		return $params;

	}

}
