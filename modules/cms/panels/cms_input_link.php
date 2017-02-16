<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_link extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		// todo: cache data here, as there might be more link inputs on the page
		// or move where fk data is prepared

		$this->load->model('cms_page_panel_model');
		$this->load->model('cms_panel_model');
		$this->load->model('cms_page_model');
		$this->load->model('cms_slug_model');

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
			$params['value'] = array(
					'url' => $params['value'],
					'target' => '_manual',
			);
		} else if (empty($params['value'])){
			$params['value'] = array(
					'target' => '_none',
			);
		}

		if(empty($params['value']['target'])){
			$params['value']['target'] = '_none';
		}

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
				
			// get lists
			// get all no page blocks
			$block_types = array();
			$blocks = $this->cms_page_panel_model->get_cms_page_panels_by(array('page_id' => [999999,0], ));
			foreach($blocks as $block){
				$block_types[$block['panel_name']] = $block['panel_name'];
			}

			// check definitions and leave only list ones - IMPORTANT link target has to be 1 in list definition
			$params['cms_page_panels'] = array();
			$block_lists = array();
			foreach($block_types as $block_type){

				$block_config = $this->cms_panel_model->get_cms_panel_config($block_type);

				if((!$special_targets && !empty($block_config['list']) && !empty($block_config['list']['link_target'])) // if normal lists only
						|| ($special_targets && in_array($block_type, $targets))){										// if special targets
								
							$params['targets'][$block_type] = $block_config['list']['item_title'];
							if (empty($block_config['list']['title_field'])){
								$block_config['list']['title_field'] = 'heading';
							}
								
							// put together list select data
							foreach($blocks as $block){
								if ($block['panel_name'] == $block_type){
										
									// if no title field, use title
									if (empty($block[$block_config['list']['title_field']])){
										$block_title = $block['title'];
									} else {
										$block_title = $block[$block_config['list']['title_field']];
									}

									$params['lists'][$block_type][$block['block_id']] = substr($block_title, 0, 80);

									// slug data
									$block_target = $block_type.'='.$block['block_id'];
									$block_slug = $this->cms_slug_model->get_cms_slug_by_target($block_target);
									$params['slugs'][$block_type][$block['block_id']] = !empty($block_slug) ? $block_slug.'/' : $block_target;
										
								}
							}
								
				}

				// collect lists for excluding from pages
				if(!empty($block_config['list'])){
					$block_lists[$block_type] = $block_type;
				}

			}
				
		}

		if (!empty($GLOBALS['config']['input_link_order'])){
			foreach($params['lists'] as &$list){
				natcasesort($list);
			}
		}

		$params['pages'] = $this->cms_page_model->get_cms_pages();
		if (!empty($block_lists)){
			foreach($params['pages'] as $key => $page){
				if (in_array($page['slug'], $block_lists)){
					unset($params['pages'][$key]);
				}
			}
		}

		if (stristr($params['name'], '[]')){
			$params['name'] = str_replace('[]', '', $params['name']);
			$params['name_extra'] = '[]';
		} else {
			$params['name_extra'] = '';
		}

		return $params;

	}

}
