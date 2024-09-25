<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_link extends CI_Controller {

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

		// todo: cache data here, as there might be more link inputs on the page
		// or move where fk data is prepared

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_slug_model');

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

		// get lists
		$lists = $this->cms_page_panel_model->get_lists();

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

			// check definitions and leave only list ones - IMPORTANT link target has to be 1 in list definition
			$params['cms_page_panels'] = array();
			$block_lists = array();
			foreach($lists as $list){

				$block_config = $this->cms_panel_model->get_cms_panel_config($list);

				if((!$special_targets && !empty($block_config['list']['link_target'])) 	// if normal lists only
						|| ($special_targets && in_array($list, $targets))){		// if special targets
								
					$params['targets'][$list] = $block_config['list']['item_title'];
					if (empty($block_config['list']['title_field'])){
						$block_config['list']['title_field'] = 'heading';
					}
					
					$list_pages = $this->cms_page_panel_model->get_list($list);
						
					// put together list select data
					foreach($list_pages as $list_page){

						// if no title field, use title
						if (empty($list_page[$block_config['list']['title_field']])){
							$block_title = $list_page['_panel_heading'];
						} else {
							$block_title = !empty($list_page['heading']) ? $list_page['heading'] : ($list.'='.$list_page['cms_page_panel_id']);
						}

						$params['lists'][$list][$list_page['cms_page_panel_id']] = mb_substr($block_title, 0, 40);

						// slug data
						$block_target = $list.'='.$list_page['cms_page_panel_id'];
						$block_slug = $this->cms_slug_model->get_cms_slug_by_target($block_target);
						$params['slugs'][$list][$list_page['cms_page_panel_id']] = !empty($block_slug) ? $block_slug.'/' : $block_target;
								
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

		$params['pages'] = $this->cms_page_model->get_cms_pages();

		$lists_old = [];
		foreach($lists as $list){
			list($module, $list_name) = explode('/', $list);
			$lists_old[] = $list_name;
		}

		foreach($params['pages'] as $key => $page){
			if (in_array($page['slug'], $lists) || in_array($page['slug'], $lists_old) || !in_array($page['position'], ['main','']) ){
				unset($params['pages'][$key]);
			}
		}

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
