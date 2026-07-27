<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Admin select of module provides for a service (e.g. ai, shop_checkout → panel names).
 */
class cms_input_provides extends \Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_input_select.scss');

	}

	function panel_params($params){

		$service = $params['service'] ?? ($params['params']['service'] ?? '');

		$values = ['' => '-- none --'];

		$providers = ($service !== '' && !empty($GLOBALS['config']['provides'][$service]))
				? $GLOBALS['config']['provides'][$service]
				: [];

		if (is_array($providers)){
			if (isset($providers['panel']) && is_string($providers['panel'])){
				$panel = $providers['panel'];
				$values[$panel] = $providers['label'] ?? $panel;
			} else {
				foreach ($providers as $key => $provider){
					if (!is_array($provider)){
						continue;
					}
					$panel = $provider['panel'] ?? (is_string($key) ? $key : '');
					if ($panel === ''){
						continue;
					}
					$values[$panel] = $provider['label'] ?? $panel;
				}
			}
		}

		$params['values'] = $values;
		$params['service'] = $service;

		if (!empty($params['params']['add_empty'])){
			$params['add_empty'] = $params['params']['add_empty'];
		}
		if (!empty($params['params']['mandatory'])){
			$params['mandatory'] = $params['params']['mandatory'];
			if (empty($params['mandatory_class'])){
				$params['mandatory_class'] = ' cms_input_mandatory ';
			}
		}

		if (!empty($params['params']['groups'])){
			$params['groups'] = $params['params']['groups'];
			if (!is_array($params['groups'])){
				$params['groups'] = [$params['groups']];
			}
		}

		return $params;

	}

}
