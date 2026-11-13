<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_repeater_select extends \Controller {

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
	 * A select whose options are rows of another repeater (target) in the same form; the options are
	 * filled in the browser (cms_input_repeater_select.js) from data-* on the select.
	 */
	function panel_params($params){

		$params['select_params'] = [
				'label' => $params['label'],
				'value' => $params['value'] ?? '',
				'values' => [],
				'name' => $params['name'],
				'name_clean' => $params['name_clean'],
				'extra_class' => ' cms_input_repeater_select',
				'mandatory_class' => '',
				'help' => $params['help'],
				'extra_data' => [
						'selected' => $params['value'] ?? '',
						'target' => $params['target'] ?? '',
						'field' => !empty($params['field']) ? $params['field'] : 'heading',
						'add_empty' => !empty($params['add_empty']) ? '1' : '0',
						'labels' => !empty($params['labels']) ? $params['labels'] : '',
				],
		];

		return $params;

	}

}
