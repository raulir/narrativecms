<?php

namespace imagemaker;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class transform_picker extends \Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('cms/cms_input.scss');
		add_css('cms/cms_popup.scss');
		add_css('modules/imagemaker/css/transform_picker.scss');

		$GLOBALS['_panel_js'][] = [
				'script' => 'modules/cms/js/cms_popup.js',
				'sync' => 'defer',
		];
		$GLOBALS['_panel_js'][] = 'modules/imagemaker/js/transform_picker.js';

	}

	function panel_params($params){

		$params['image'] = $params['image'] ?? '';
		$params['value'] = $params['value'] ?? '';
		if (is_array($params['value'])){
			$params['value'] = json_encode($params['value']);
		}
		$params['points'] = (int)($params['points'] ?? 5);
		if ($params['points'] < 2){
			$params['points'] = 5;
		}

		return $params;

	}

}
