<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_toolbar extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){
		
//		print_r($params);
			
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_panel_model');

		$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['target_id']);

		if (!empty($params['target_parent_id'])) {
			$cms_page_panel['target_parent_id'] = $params['target_parent_id'];
		}

		// load panel config
		if (!empty($cms_page_panel['panel_name'])){
			$panel_config = $this->cms_panel_model->get_cms_panel_config($cms_page_panel['panel_name']);
		} else {
			$panel_config = [];
		}

		// breadcrumb
		$params['breadcrumb'] = [];

		if (!empty($cms_page_panel['parent_id'])){

			$parent = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel['parent_id']);

			// deprecate
			if ($parent['cms_page_id'] == 999999){
				$parent['cms_page_id'] = 0;
			}
			
			if (empty($params['target_page_id'])){
				$params['target_page_id'] = $parent['cms_page_id'];
			}

			if (!empty($parent['cms_page_id'])){

				$params['page'] = $this->cms_page_model->get_page($params['target_page_id']);
				$params['breadcrumb'][] = [ // root url to pages
						'text' => 'Pages',
						'url' => 'admin/',
				];

				$params['breadcrumb'][] = [ // to page
						'text' => !empty($params['page']['title']) ? $params['page']['title'] : '[ no title ]',
						'url' => 'admin/page/'.$params['page']['cms_page_id'].'/',
				];

			} else { // list item

				// if this is list item
				if (!empty($panel_config['list'])){
						
					$params['breadcrumb'][] = [ // url to list root
							'text' => $panel_config['list']['list_title'],
							'url' => 'admin/cms_list/'.$parent['panel_name'].'/',
					];

				}

			}

			$params['breadcrumb'][] = [ // parent block
					'text' => $parent['title'],
					'url' => 'admin/cms_page_panel/'.$parent['cms_page_panel_id'].'/',
			];
			
			$params['breadcrumb'][] = [ // current block
					'text' => $params['target_id'] ? $cms_page_panel['title'] : 'New panel',
					'url' => '',
			];
				
		} else if (!empty($params['target_page_id'])){ // normal panel on page

			$params['page'] = $this->cms_page_model->get_page($params['target_page_id']);

			$params['breadcrumb'] = [
					[ // root url to pages
							'text' => 'Pages',
							'url' => 'admin/',
					],
					[ // to page
							'text' => !empty($params['page']['title']) ? $params['page']['title'] : '[ no title ]',
							'url' => 'admin/page/'.$params['target_page_id'].'/',
					],
					[ // current block
							'text' => $params['target_id'] ? $cms_page_panel['title'] : 'New panel',
							'url' => '',
					],
			];

		} else {

			// if this is list item
			if (!empty($panel_config['list'])){

				if (empty($panel_config['parent'])){
					
					$params['breadcrumb'] = [
							[ // url to list root
									'text' => $panel_config['list']['list_title'],
									'url' => 'admin/cms_list/'.str_replace('/', '__', $cms_page_panel['panel_name']).'/',
							],
					];
					
				} else {
					
					$params['breadcrumb'] = [
							[ // url to list root
									'text' => $panel_config['parent']['label'],
									'url' => $panel_config['parent']['url'],
							],
					];
					
				}
				
				// current block
				if ($cms_page_panel['sort']){
					// not settings
					$heading = $this->run_panel_method($cms_page_panel['panel_name'], 'panel_heading', 
							array_merge($cms_page_panel, ['_heading_type' => 'short']));
				} else {
					$heading = (!empty($panel_config['label']) ? $panel_config['label'] : $cms_page_panel['panel_name']) . ' settings';
				}
				
				$params['breadcrumb'][] = [
						'text' => $heading,
						'url' => '',
						'field' => !empty($panel_config['list']['title_field']) ? $panel_config['list']['title_field'] : 'heading', // title field
				];

			} else { // must be global settings panel

				$params['breadcrumb'] = [
						[ // url to list root
								'text' => !empty($panel_config['label']) ? $panel_config['label'] : '[unknown]',
								'url' => '',
						],
				];

			}

		}

		// buttons
		if (!empty($panel_config['list']['extra_buttons'])){
			$params['buttons'] = $panel_config['list']['extra_buttons'];
		} else {
			$params['buttons'] = [];
		}

		// all page panels can be saved
		$params['buttons'][] = ['name' => 'cms_page_panel_button_save', 'position' => 'visible', ];

		// delete,caching,hide = except not on page && list item == settings
		if (!empty($cms_page_panel['cms_page_id']) || !empty($cms_page_panel['parent_id']) || !empty($panel_config['list'])){
			
			$params['buttons'][] = [
					'name' => 'cms_page_panel_button_delete', 
					'position' => 'hidden', 
					'cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'],
			];
			
			$params['buttons'][] = [
					'name' => 'cms_page_panel_button_caching', 
					'position' => 'hidden', 
					'cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'],
			];
			
			$params['buttons'][] = [
					'name' => 'cms_page_panel_button_show', 
					'position' => 'visible', 
					'cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'],
			];
			
			$params['buttons'][] = [
					'name' => 'cms_page_panel_button_export', 
					'position' => 'hidden', 
					'cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'],
			];
			
			$params['hidden_section'] = 1;
		
		}
		
		// if panel has global settings
		if (!empty($panel_config['settings']) && empty($panel_config['list']) && 
				(!empty($cms_page_panel['cms_page_id']) || !empty($cms_page_panel['parent_id']) || !empty($cms_page_panel['sort']))){
			
			$params['buttons'][] = ['name' => 'cms_page_panel_button_settings', 'position' => 'hidden', ];
			$params['hidden_section'] = 1;
		
		}

		// if panel can have target groups assigned
		if (!empty($_SESSION['config']['targets']) && empty($panel_config['list']) && 
				(!empty($cms_page_panel['cms_page_id']) || !empty($cms_page_panel['parent_id']))){
				
			$params['buttons'][] = ['name' => 'cms_page_panel_button_targets', 'position' => 'hidden', ];
			$params['hidden_section'] = 1;
		
		}
		
		return $params;

	}

}
