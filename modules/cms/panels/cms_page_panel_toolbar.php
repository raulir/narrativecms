<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_toolbar extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		// deprecate
		if ($params['cms_page_id'] == 999999) $params['cms_page_id'] = 0;

		$this->load->model('cms_page_panel_model');
		$this->load->model('cms_page_model');
		$this->load->model('cms_panel_model');

		$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['cms_page_panel_id']);
		// deprecate
		if ($cms_page_panel['page_id'] == 999999) $cms_page_panel['page_id'] = 0;

		if (!empty($params['parent_id'])) {
			$cms_page_panel['parent_id'] = $params['parent_id'];
		}

		// breadcrumb
		if (empty($params['breadcrumb']) || count($params['breadcrumb']) == 0){

			$params['breadcrumb'] = [];

			if (!empty($cms_page_panel['parent_id'])){

				$parent = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel['parent_id']);

				// deprecate
				if ($parent['page_id'] == 999999){
					$parent['page_id'] = 0;
				}
				
				if (empty($params['cms_page_id'])){
					$params['cms_page_id'] = $parent['page_id'];
				}

				if (!empty($parent['page_id'])){

					$params['page'] = $this->cms_page_model->get_page($params['cms_page_id']);
					$params['breadcrumb'][] = [ // root url to pages
							'text' => 'Pages',
							'url' => 'admin/',
					];

					$params['breadcrumb'][] = [ // to page
							'text' => str_limit(!empty($params['page']['title']) ? $params['page']['title'] : '[ no title ]', 30),
							'url' => 'admin/page/'.$params['page']['cms_page_id'].'/',
					];

				} else { // list item

					// check if list item
					$panel_config = $this->cms_panel_model->get_cms_panel_config($parent['panel_name']);

					// if this is list item
					if (!empty($panel_config['list'])){
							
						$params['breadcrumb'][] = [ // url to list root
								'text' => $panel_config['list']['list_title'],
								'url' => 'admin/cms_list/'.$parent['panel_name'].'/',
						];

					}

				}

				$params['breadcrumb'][] = [ // parent block
						'text' => str_limit($parent['title'], 30),
						'url' => 'admin/block/'.$parent['cms_page_panel_id'].'/',
				];
				$params['breadcrumb'][] = [ // current block
						'text' => $params['cms_page_panel_id'] ? str_limit($cms_page_panel['title'], 40) : 'New panel',
						'url' => '',
				];
					
			} else if (!empty($params['cms_page_id'])){ // normal panel on page

				$params['page'] = $this->cms_page_model->get_page($params['cms_page_id']);

				$params['breadcrumb'] = [
						[ // root url to pages
								'text' => 'Pages',
								'url' => 'admin/',
						],
						[ // to page
								'text' => str_limit(!empty($params['page']['title']) ? $params['page']['title'] : '[ no title ]', 40),
								'url' => 'admin/page/'.$params['cms_page_id'].'/',
						],
						[ // current block
								'text' => $params['cms_page_panel_id'] ? str_limit($cms_page_panel['title'], 40) : 'New panel',
								'url' => '',
						],
				];

			} else {

				// check if list item
				$panel_config = $this->cms_panel_model->get_cms_panel_config($cms_page_panel['panel_name']);

				// if this is list item
				if (!empty($panel_config['list'])){

					if (empty($panel_config['parent'])){
						
						$params['breadcrumb'] = [
								[ // url to list root
										'text' => $panel_config['list']['list_title'],
										'url' => 'admin/cms_list/'.$cms_page_panel['panel_name'].'/',
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
					$params['breadcrumb'][] = [
							'text' => str_limit(!empty($panel_config['list']['title_field']) ? $cms_page_panel[$panel_config['list']['title_field']] : $cms_page_panel['heading'], 40),
							'url' => '',
							'field' => !empty($panel_config['list']['title_field']) ? $panel_config['list']['title_field'] : 'heading', // title field
					];

				} else { // must be global settings panel

					$params['breadcrumb'] = [
							[ // url to list root
									'text' => !empty($panel_config['label']) ? $panel_config['label'] : $cms_page_panel['title'],
									'url' => '',
							],
					];

				}

			}

		}

		// buttons
		if (!empty($panel_config['list']['extra_buttons'])){
			$params['buttons'] = $panel_config['list']['extra_buttons'];
		} else {
			$params['buttons'] = [];
		}

		// all page panels can be saved
		$params['buttons'][] = 'cms_page_panel_button_save';

		// delete,caching,hide = except not on page && list item == settings
		if (!empty($cms_page_panel['page_id']) || !empty($cms_page_panel['parent_id']) || !empty($panel_config['list'])){
			$params['buttons'][] = 'cms_page_panel_button_delete';
			$params['buttons'][] = 'cms_page_panel_button_caching';
			$params['buttons'][] = 'cms_page_panel_button_show';
		}

		return $params;

	}

}
