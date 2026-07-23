<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_toolbar extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_slug_model');

		$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['target_id'], $this->cms_page_panel_model->get_cms_language());
		if (empty($cms_page_panel) || !is_array($cms_page_panel)){
			$cms_page_panel = [];
		}

		// New panel (target_id 0): caller may pass panel_name / page ids
		if (empty($cms_page_panel['panel_name']) && !empty($params['panel_name'])){
			$cms_page_panel['panel_name'] = $params['panel_name'];
		}
		if (empty($cms_page_panel['cms_page_id']) && isset($params['target_page_id'])){
			$cms_page_panel['cms_page_id'] = $params['target_page_id'];
		}

		$params['cms_page_panel_id'] = !empty($cms_page_panel['cms_page_panel_id'])
				? $cms_page_panel['cms_page_panel_id']
				: (int)($params['target_id'] ?? 0);

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

		// Global / list panel settings: cms_page_id 0, parent_id 0, sort 0
		$is_panel_settings = empty($cms_page_panel['cms_page_id'])
				&& empty($cms_page_panel['parent_id'])
				&& empty($cms_page_panel['sort']);

		$toolbar_title_item = function($title) use ($cms_page_panel){
			if (empty($cms_page_panel['cms_page_panel_id'])){
				return [
						'text' => $title,
						'url' => '',
						'class' => 'cms_page_panel_toolbar_title',
				];
			}
			$admin_title = $this->cms_page_panel_model->get_panel_admin_title($cms_page_panel);
			return [
					'text' => $admin_title !== '' ? $admin_title : $title,
					'url' => '',
					'class' => 'cms_page_panel_toolbar_title',
			];
		};

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

			$parent_title = $this->cms_page_panel_model->get_panel_admin_title($parent);
			if ($parent_title === ''){
				$parent_title = $parent['title'];
			}

			$params['breadcrumb'][] = [ // parent block
					'text' => $parent_title,
					'url' => 'admin/cms_page_panel/'.$parent['cms_page_panel_id'].'/',
			];
			
			$params['breadcrumb'][] = $toolbar_title_item($params['target_id'] ? '' : 'New panel');
				
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
					$toolbar_title_item($params['target_id'] ? '' : 'New panel'),
			];

		} else {

			// list panel settings (sort 0): single title, e.g. "Product category settings"
			if (!empty($panel_config['list']) && $is_panel_settings){

				if (!empty($panel_config['list']['item_title'])){
					$settings_heading = $panel_config['list']['item_title'].' settings';
				} else {
					$settings_heading = (!empty($panel_config['label']) ? $panel_config['label'] : $cms_page_panel['panel_name']).' settings';
				}

				$params['breadcrumb'] = [
						[
								'text' => $settings_heading,
								'url' => '',
						],
				];

			// list item (sort > 0)
			} else if (!empty($panel_config['list'])){

				if (empty($panel_config['parent'])){
					
					$params['breadcrumb'] = [
							[
									'text' => $panel_config['list']['list_title'],
									'url' => 'admin/cms_list/'.str_replace('/', '__', $cms_page_panel['panel_name']).'/',
							],
					];
					
				} else {
					
					$params['breadcrumb'] = [
							[
									'text' => $panel_config['parent']['label'],
									'url' => $panel_config['parent']['url'],
							],
					];
					
				}

				$heading = $this->cms_page_panel_model->get_panel_admin_title($cms_page_panel);
				if ($heading === ''){
					$heading = $cms_page_panel['title'];
				}
				$params['breadcrumb'][] = $toolbar_title_item($heading);

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
		$params['buttons'][] = [
				'name' => 'cms/cms_page_panel_button_save', 
				'position' => 'visible', 
		];

		// delete, caching, hide — page panels, children, list items; never global/list settings
		if (!$is_panel_settings && (
				!empty($cms_page_panel['cms_page_id'])
				|| !empty($cms_page_panel['parent_id'])
				|| !empty($panel_config['list']))){
			
			$btn_id = $cms_page_panel['cms_page_panel_id'] ?? $params['cms_page_panel_id'] ?? 0;
			$btn_panel = $cms_page_panel['panel_name'] ?? ($params['panel_name'] ?? '');

			$params['buttons'][] = [
					'name' => 'cms/cms_page_panel_button_delete', 
					'position' => 'hidden', 
					'cms_page_panel_id' => $btn_id,
					'panel_name' => $btn_panel,
			];
			
			$params['buttons'][] = [
					'name' => 'cms/cms_page_panel_button_caching', 
					'position' => 'hidden', 
					'cms_page_panel_id' => $btn_id,
					'panel_name' => $btn_panel,
			];
			
			$params['buttons'][] = [
					'name' => 'cms/cms_page_panel_button_show', 
					'position' => 'visible', 
					'cms_page_panel_id' => $btn_id,
					'panel_name' => $btn_panel,
			];
			
			$params['hidden_section'] = 1;
		
		}

		if (!empty($cms_page_panel['cms_page_panel_id'])){

			$params['buttons'][] = [
					'name' => 'cms/cms_page_panel_button_export',
					'position' => 'hidden',
					'cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'],
					'panel_name' => $cms_page_panel['panel_name'] ?? '',
			];
			$params['hidden_section'] = 1;

			if (empty($cms_page_panel['cms_page_id']) && !empty($cms_page_panel['sort'])
					&& !empty($panel_config['list']['link_target'])){

				$slug_target = $cms_page_panel['panel_name'].'='.$cms_page_panel['cms_page_panel_id'];
				$current_slug = $this->cms_slug_model->get_cms_slug_by_target($slug_target);

				if ($current_slug !== ''){
					$params['buttons'][] = [
							'name' => 'cms/cms_page_panel_button_edit_slug',
							'position' => 'hidden',
							'cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'],
							'panel_name' => $cms_page_panel['panel_name'] ?? '',
					];
					$params['hidden_section'] = 1;
				}

			}

		}
		
		// if panel has global settings
		if (!empty($panel_config['settings']) && empty($panel_config['list']) && 
				(!empty($cms_page_panel['cms_page_id']) || !empty($cms_page_panel['parent_id']) || !empty($cms_page_panel['sort']))){
			
			$params['buttons'][] = [
					'name' => 'cms/cms_page_panel_button_settings', 
					'position' => 'hidden', 
					'panel_name' => $cms_page_panel['panel_name'] ?? ($params['panel_name'] ?? ''),
			];
			$params['hidden_section'] = 1;
		
		}

		// if panel can have target groups assigned
		if (!empty($_SESSION['config']['targets']) && empty($panel_config['list']) && 
				(!empty($cms_page_panel['cms_page_id']) || !empty($cms_page_panel['parent_id']))){
				
			$params['buttons'][] = [
					'name' => 'cms/cms_page_panel_button_targets', 
					'position' => 'hidden', 
					'cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'] ?? $params['cms_page_panel_id'] ?? 0,
					'panel_name' => $cms_page_panel['panel_name'] ?? ($params['panel_name'] ?? ''),
			];
			$params['hidden_section'] = 1;
		
		}
		
		if (!empty($params['extra_buttons'])){
			foreach($params['extra_buttons'] as $bkey => $button){
				$params['extra_buttons'][$bkey]['cms_page_panel_id'] =
						$cms_page_panel['cms_page_panel_id'] ?? $params['cms_page_panel_id'] ?? 0;
				$params['extra_buttons'][$bkey]['panel_name'] =
						$cms_page_panel['panel_name'] ?? ($params['panel_name'] ?? '');
			}
		}

		return $params;

	}

}
