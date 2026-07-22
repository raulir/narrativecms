<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Select one list panel type (module/panel with definition "list").
 * Field option link_target: "1" = only lists with list.link_target; "0"/absent = all lists.
 * Labels: Shop/Product (ucfirst each path segment).
 */
class cms_input_list extends \Controller {

	function __construct(){

		parent::__construct();

		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_input_select.scss');

	}

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');

		if (!empty($params['params']['add_empty'])){
			$params['add_empty'] = $params['params']['add_empty'];
		}
		if (!empty($params['params']['mandatory'])){
			$params['mandatory'] = $params['params']['mandatory'];
			if (empty($params['mandatory_class'])){
				$params['mandatory_class'] = ' cms_input_mandatory ';
			}
		}

		// link_target: 1 = only lists with list.link_target; 0 = all lists
		$link_target_only = false;
		if (isset($params['params']['link_target'])){
			$link_target_only = (string)$params['params']['link_target'] === '1'
					|| $params['params']['link_target'] === 1
					|| $params['params']['link_target'] === true;
		} else if (isset($params['link_target'])){
			$link_target_only = (string)$params['link_target'] === '1'
					|| $params['link_target'] === 1
					|| $params['link_target'] === true;
		}

		$add_empty = !empty($params['add_empty']) || !empty($params['mandatory']);

		$lists = $this->cms_page_panel_model->get_lists();
		if (!is_array($lists)){
			$lists = [];
		}

		$values = [];
		foreach ($lists as $list_id => $_unused){
			// get_lists may return value-as-key; normalise to module/panel string
			$id = is_string($list_id) && strpos($list_id, '/') !== false
					? $list_id
					: (string)$_unused;
			if ($id === '' || strpos($id, '/') === false){
				continue;
			}
			if ($link_target_only){
				$config = $this->cms_panel_model->get_cms_panel_config($id);
				if (empty($config['list']['link_target'])){
					continue;
				}
			}
			$values[$id] = $this->_list_label($id);
		}

		// Stable alpha by label
		asort($values, SORT_NATURAL | SORT_FLAG_CASE);

		if ($add_empty){
			$values = ['' => '-- not specified --'] + $values;
		}

		if (empty($values)){
			$values = ['' => '-- no lists --'];
		}

		$params['values'] = $values;

		return $params;

	}

	/**
	 * shop/product → Shop/Product
	 */
	function _list_label($list_id){

		$parts = explode('/', $list_id, 2);
		$out = [];
		foreach ($parts as $part){
			$out[] = ucfirst($part);
		}
		return implode('/', $out);

	}

}
