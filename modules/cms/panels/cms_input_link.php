<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_link extends \Controller {

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

		// Lists, list items, slugs and pages are read once per request (cms_page_panel_cms_model)
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_page_panel_cms_model');
		$link_targets = $this->cms_page_panel_cms_model->get_link_targets();

		// check if targets are limited
		if (!empty($params['targets'])){
			$targets = explode(',', $params['targets']);
		} else {
			$targets = array('none', 'manual', 'page', 'lists', );
		}

		// figure out, if there is any weird targets (specific list names)
		$special_targets = false;
		foreach($targets as $target){
			if (!in_array($target, array('none', 'manual', 'page', 'lists', ))){
				$special_targets = true;
			}
		}

		// backwards compatibility
		if (!empty($params['value']) && is_string($params['value'])){
			$params['value'] = [
					'url' => $params['value'],
					'target' => '_manual',
			];
		} else if (empty($params['value'])){
			$params['value'] = [
					'target' => '_none',
			];
		}

		if(empty($params['value']['target'])){
			$params['value']['target'] = '_none';
		}

		$lists = $link_targets['lists'];

		// possible targets
		$params['targets'] = array();

		if (in_array('none', $targets)){
			$params['targets']['_none'] = '-- no link --';
		}

		if (in_array('manual', $targets)){
			$params['targets']['_manual'] = '> manual';
		}

		if (in_array('page', $targets)){
			$params['targets']['_page'] = '> page';
		}

		if (in_array('lists', $targets) || $special_targets){

			// Normal lists only when their definition has list.link_target; special targets name their lists
			$params['cms_page_panels'] = [];
			foreach($lists as $list){

				$block_config = $this->cms_panel_model->get_cms_panel_config($list);

				if((!$special_targets && !empty($block_config['list']['link_target']))
						|| ($special_targets && in_array($list, $targets))){

					$link_list = $this->cms_page_panel_cms_model->get_link_list($list);
					$params['targets'][$list] = $link_list['item_title'];
					if ($link_list['items']){
						$params['lists'][$list] = $link_list['items'];
						$params['slugs'][$list] = $link_list['slugs'];
					}

				}

			}

		}

		if (empty($params['lists'])){
			$params['lists'] = [];
		}
		if (!empty($GLOBALS['config']['input_link_order'])){
			foreach($params['lists'] as &$l){
				natcasesort($l);
			}
		}

		$params['pages'] = $link_targets['pages'];

		if (stristr($params['name'], '[]')){
			$params['name'] = str_replace('[]', '', $params['name']);
			$params['name_extra'] = '[]';
		} else {
			$params['name_extra'] = '';
		}
		
		// _value
		$params['_value'] = '';
		if ($params['value']['target'] == '_page'){
			$params['_value'] = !empty($params['value']['cms_page_id']) ? $params['value']['cms_page_id'] : 0;
		} elseif ($params['value']['target'] == '_list'){
			$params['_value'] = $params['value']['url'];
		} elseif ($params['value']['target'] == '_manual'){
			$params['_value'] = $params['value']['url'];
		}
		
		unset($params['panel_structure']);

		return $params;

	}

}
