<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_multi extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		
	}
	
	function panel_params($params){

		if (empty($params['value']) || !is_array($params['value'])){
			$params['value'] = [];
		}

		// sticky: same shape as values — key => label; always shown first, not removable/draggable
		if (empty($params['sticky']) || !is_array($params['sticky'])){
			$params['sticky'] = [];
		}
		if (empty($params['values']) || !is_array($params['values'])){
			$params['values'] = [];
		}

		foreach ($params['sticky'] as $skey => $slabel){
			if (!isset($params['values'][$skey]) || $params['values'][$skey] === ''){
				$params['values'][$skey] = $slabel !== '' && $slabel !== null ? $slabel : $skey;
			}
		}

		$sticky_keys = array_map('strval', array_keys($params['sticky']));
		$value = [];
		foreach ($params['value'] as $v){
			if (is_array($v)){
				$v = $v['value'] ?? $v['name'] ?? reset($v);
			}
			$v = (string)$v;
			if ($v === '' || in_array($v, $sticky_keys, true)){
				continue;
			}
			if (!isset($params['values'][$v])){
				continue;
			}
			$value[] = $v;
		}

		// Sticky keys first (fixed order of sticky map), then free items
		$params['value'] = array_merge($sticky_keys, $value);
		$params['sticky_keys'] = $sticky_keys;

		return $params;

	}

}
