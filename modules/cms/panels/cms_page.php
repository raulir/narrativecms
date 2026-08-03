<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('cms/cms_page_panel_toolbar.scss');
	
	}

	function panel_params($params){

		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_panel_cms_model');
		$this->load->model('cms/cms_user_model');

		$return['block_list'] = [];
		$return['cms_page_panels'] = [];
		
		$cms_language = !empty($_SESSION['cms_language']) ? $_SESSION['cms_language'] : false;

		if ($params['cms_page_id']){
				
			$return['page'] = $this->cms_page_model->get_page($params['cms_page_id'], $cms_language);
			$return['cms_page_panels'] = $this->cms_page_panel_model->get_cms_page_panels_by([
					'cms_page_id' => $params['cms_page_id'],
					'_fields' => ['cms_page_panel_id', 'panel_name', 'title', 'cms_page_id', 'sort', 'show'],
			]);
			$return['block_list'] = array_column($return['cms_page_panels'], 'cms_page_panel_id');

		} else {
				
			$return['page'] = $this->cms_page_model->new_page();
			$return['page']['position'] = !empty($params['position']) ? $params['position'] : 'main';
			$return['page']['title'] = 'New '.(!empty($params['position']) ? $params['position'] : 'page');
		}

		$page_class = $this->cms_page_model->get_page_class($return['page']);
		$return['page_class'] = $page_class;
		$return['is_list_item'] = ($page_class === 'list'
				|| (!empty($return['page']['slug']) && $this->cms_page_panel_cms_model->is_list_slug($return['page']['slug'])))
			? 1 : 0;

		$position = !empty($return['page']['position']) ? $return['page']['position'] : 'main';
		$panel_count = count($return['cms_page_panels'] ?? []);
		$page_status = !empty($return['page']['status']) ? (int)$return['page']['status'] : 0;

		// Hide: main pages that are not system (user + list templates)
		$return['can_hide'] = ($position === 'main' || $position === '') && $page_class !== 'system' ? 1 : 0;
		// Delete in gear: user + list pages (not system); enabled when page_can_delete
		$return['show_delete_control'] = ($page_class === 'user' || $page_class === 'list') ? 1 : 0;
		$return['can_delete'] = $this->cms_page_model->page_can_delete($return['page']) ? 1 : 0;
		$return['panel_count'] = $panel_count;
		$return['page_status'] = $page_status;

		if (!empty($return['page']['create_cms_user_id'])) {
			$return['page']['create_user'] = $this->cms_user_model->get_cms_user($return['page']['create_cms_user_id']);
		}
		if (empty($return['page']['create_user'])) {
			$return['page']['create_user'] = [];
		}
		if (!empty($return['page']['update_cms_user_id'])) {
			$return['page']['update_user'] = $this->cms_user_model->get_cms_user($return['page']['update_cms_user_id']);
		}
		if (empty($return['page']['update_user'])) {
			$return['page']['update_user'] = [];
		}
		
		// layout
		$return['cms_page_layout'] = !empty($return['page']['layout']) ? $return['page']['layout'] : 
				(!empty($GLOBALS['config']['layout']) ? $GLOBALS['config']['layout'] : 'cms/rem');

		return $return;

	}

}
