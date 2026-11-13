<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * The one way an admin input is rendered: from print_fields() for definition fields, and from admin
 * templates for their own fields (type + the input's params). Picks the input panel for the type,
 * adds the params every input shares, and wraps it by the input's "input" flags (cms_input.md).
 */
class cms_input extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->load->helper('cms/cms_fields_helper');

	}

	function panel_params($params){

		$type = (string)($params['type'] ?? '');
		$params['panel_name'] = cms_input_panel_name($type);
		if (stristr($type, '/')){
			list($params['module'], $rest) = explode('/', $type);
		}

		$flags = cms_input_flags($params['panel_name']);
		$params['input_container'] = $flags['container'];

		// Values go into html attributes as they are stored; " is the one character that would end one.
		// Module inputs and inputs that declare value_raw get the value as it is.
		if (empty($flags['value_raw'])){
			if (!stristr($type, '/') && isset($params['value']) && is_string($params['value'])){
				$params['value'] = str_replace('"', '&quot;', $params['value']);
			}
			if (isset($params['default']) && !is_array($params['default'])){
				$params['default'] = str_replace('"', '&quot;', $params['default']);
			}
		}

		$params['mandatory_class'] = !empty($params['mandatory']) ? ' cms_input_mandatory ' : '';
		$params['label'] = (array_key_exists('label', $params) ? (string)$params['label'] : '[no label]')
				.(!empty($params['mandatory']) ? ' *' : '');
		$params['help'] = !empty($params['help']) ? $params['help'] : '';
		$params['readonly'] = $params['readonly'] ?? '0';

		if (!empty($params['groups']) && !is_array($params['groups'])){
			$params['groups'] = [$params['groups']];
		}
		if (empty($params['groups'])){
			$params['groups'] = [];
		}

		// What the input panel gets: these params as they are now (the template passes them on). That includes
		// this dispatcher's access pass (_access_ok): the input type comes from a definition, never from the
		// request, and panel "access" keys are visitor keys, which an admin form must not be judged by.
		$params['input_params'] = $params;

		return $params;

	}

}
