<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_select extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
	
	}
	
	/**
	 * Also rendered inside other inputs (layout, repeater_select) with the same keys.
	 */
	function panel_params($params){

		$params['values'] = is_array($params['values'] ?? null) ? $params['values'] : [];

		if (!empty($params['mandatory']) && empty($params['mandatory_class'])){
			$params['mandatory_class'] = ' cms_input_mandatory ';
		}

		$add_empty = !empty($params['add_empty']) || !empty($params['mandatory']);

		if ($add_empty && empty($params['values']['']) && empty($params['values'][0])){
			$params['values'] = ['' => '-- not specified --'] + $params['values'];
		}

		return $params;

	}
	
}
