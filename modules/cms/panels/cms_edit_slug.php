<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_edit_slug extends CI_Controller {

	function __construct(){

		parent::__construct();

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function _resolve_list_item_slug_context($cms_page_panel_id){

		$cms_page_panel_id = (int)$cms_page_panel_id;

		if ($cms_page_panel_id < 1){
			return array('error' => 'Invalid panel');
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_slug_model');

		$row = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id, '', false);

		if (empty($row['cms_page_panel_id'])){
			return array('error' => 'Panel not found');
		}

		if (!empty($row['cms_page_id']) || empty($row['sort'])){
			return array('error' => 'Not a list item');
		}

		$panel_config = $this->cms_panel_model->get_cms_panel_config($row['panel_name']);

		if (empty($panel_config['list']['link_target'])){
			return array('error' => 'List item has no URL');
		}

		$target = $row['panel_name'].'='.$cms_page_panel_id;
		$slug_row = $this->cms_slug_model->get_slug_row_by_target($target);

		if ($slug_row === false){
			return array('error' => 'Slug not found');
		}

		return array(
			'cms_page_panel_id' => $cms_page_panel_id,
			'panel_name' => $row['panel_name'],
			'target' => $target,
			'current_slug' => $slug_row['cms_slug_id'] ?? '',
		);

	}

	function panel_action($params){

		$do = $this->input->post('do');
		$cms_page_panel_id = (int)$this->input->post('cms_page_panel_id');
		$context = $this->_resolve_list_item_slug_context($cms_page_panel_id);

		if (!empty($context['error'])){
			return $context;
		}

		if ($do === 'check_slug'){

			$this->load->model('cms/cms_slug_model');

			$check = $this->cms_slug_model->check_slug_for_edit(
				$this->input->post('new_slug'),
				$context['current_slug'],
				$context['target']
			);

			return array(
				'check_status' => $check['check_status'],
				'check_message' => $check['check_message'],
			);

		}

		if ($do === 'update_slug'){

			$this->load->model('cms/cms_slug_model');
			$this->load->model('cms/cms_page_cache_model');

			$result = $this->cms_slug_model->rename_target_slug(
				$context['target'],
				$this->input->post('new_slug')
			);

			if (empty($result['ok'])){
				return array('error' => !empty($result['error']) ? $result['error'] : 'Update failed');
			}

			if (!empty($result['changed'])){
				$this->cms_page_cache_model->invalidate_slug($result['old_slug']);
				$this->cms_page_cache_model->invalidate_slug($result['slug']);
				$this->cms_page_cache_model->invalidate_list_item($context['panel_name'], $cms_page_panel_id);
			}

			return array('ok' => 1, 'slug' => $result['slug']);

		}

		if ($do === 'form'){

			add_css('modules/cms/css/cms_edit_slug.scss');

			$params['cms_page_panel_id'] = $context['cms_page_panel_id'];
			$params['current_slug'] = $context['current_slug'];
			$params['target'] = $context['target'];

			return $params;

		}

		return $params;

	}

}