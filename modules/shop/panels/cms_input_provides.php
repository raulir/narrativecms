<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Admin select of module provides for a service (e.g. shop_checkout → panel names).
 * Reuses cms/cms_input_select markup/behaviour.
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

		$service = $params['service'] ?? ($params['params']['service'] ?? 'shop_checkout');

		$values = ['' => '-- select --'];

		$providers = $GLOBALS['config']['provides'][$service] ?? [];
		if (is_array($providers)){
			// New shape: keyed by panel; legacy single entry support
			if (isset($providers['panel']) && is_string($providers['panel'])){
				$panel = $providers['panel'];
				$label = $providers['label'] ?? $panel;
				$values[$panel] = $label;
			} else {
				foreach($providers as $key => $provider){
					if (!is_array($provider)){
						continue;
					}
					$panel = $provider['panel'] ?? (is_string($key) ? $key : '');
					if ($panel === ''){
						continue;
					}
					$label = $provider['label'] ?? $panel;
					$values[$panel] = $label;
				}
			}
		}

		$params['values'] = $values;

		// Mirror cms_input_select empty/mandatory handling
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
