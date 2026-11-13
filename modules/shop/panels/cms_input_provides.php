<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @deprecated Prefer cms/cms_input_provides (provides is a CMS-level feature).
 * Kept for older definitions that still reference shop/cms_input_provides.
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

		$service = $params['service'] ?? 'shop_checkout';

		$values = ['' => '-- select --'];

		$providers = $GLOBALS['config']['provides'][$service] ?? [];
		if (is_array($providers)){
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
		$params['service'] = $service;

		if (!empty($params['mandatory']) && empty($params['mandatory_class'])){
			$params['mandatory_class'] = ' cms_input_mandatory ';
		}

		return $params;

	}

}
