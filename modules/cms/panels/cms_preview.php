<?php

namespace cms;

defined('BASEPATH') OR exit('No direct script access allowed');

class cms_preview extends \Controller {

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_slug_model');

		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_settings');

		$params['preview_available'] = 0;
		$params['preview_url'] = '';
		$params['preview_highlight_id'] = 0;
		$params['desktop_preview_width'] = !empty($settings['desktop_preview_width']) ? (int)$settings['desktop_preview_width'] : 40;
		$params['mobile_preview_width'] = !empty($settings['mobile_preview_width']) ? (int)$settings['mobile_preview_width'] : 40;
		$params['rem_px'] = !empty($GLOBALS['config']['rem_px']) ? (int)$GLOBALS['config']['rem_px'] : 1000;
		$params['rem_m_px'] = !empty($GLOBALS['config']['rem_m_px']) ? (int)$GLOBALS['config']['rem_m_px'] : 500;
		$params['rem_k'] = !empty($GLOBALS['config']['rem_k']) ? (int)$GLOBALS['config']['rem_k'] : 100;

		$context = $this->_resolve_admin_preview_context();

		if (!empty($context['preview_url'])){
			$params['preview_available'] = 1;
			$params['preview_url'] = $context['preview_url'];
			$params['preview_highlight_id'] = !empty($context['highlight_id']) ? (int)$context['highlight_id'] : 0;
		}

		add_css('modules/cms/css/cms_preview.scss');

		return $params;

	}

	function _resolve_admin_preview_context(){

		$uri = trim((string)$this->uri->uri_string(), '/');

		if (preg_match('#^admin/page/(\d+)#', $uri, $match)){

			$page = $this->cms_page_model->get_page((int)$match[1]);

			if (empty($page['cms_page_id']) || empty($page['slug'])){
				return [];
			}

			return [
				'preview_url' => $GLOBALS['config']['base_url'].$page['slug'].'/',
				'highlight_id' => 0,
			];

		}

		if (!preg_match('#^admin/cms_page_panel/(\d+)#', $uri, $match)){
			return [];
		}

		$block = $this->cms_page_panel_model->get_cms_page_panel((int)$match[1]);

		if (empty($block['cms_page_panel_id'])){
			return [];
		}

		$cms_page_id = (int)$block['cms_page_id'];

		if ($cms_page_id > 0 && $cms_page_id != 999999){

			$page = $this->cms_page_model->get_page($cms_page_id);

			if (empty($page['cms_page_id']) || empty($page['slug'])){
				return [];
			}

			return [
				'preview_url' => $GLOBALS['config']['base_url'].$page['slug'].'/',
				'highlight_id' => (int)$block['cms_page_panel_id'],
			];

		}

		if ($cms_page_id != 0 || (int)$block['parent_id'] != 0 || (int)$block['sort'] == 0){
			return [];
		}

		$panel_definition = $this->cms_panel_model->get_cms_panel_config($block['panel_name']);

		if (empty($panel_definition['list']['link_target'])){
			return [];
		}

		$target = $block['panel_name'].'='.$block['cms_page_panel_id'];
		$slug = $this->cms_slug_model->get_cms_slug_by_target($target);

		if ($slug === ''){
			return [];
		}

		return [
			'preview_url' => $GLOBALS['config']['base_url'].$slug.'/',
			'highlight_id' => (int)$block['cms_page_panel_id'],
		];

	}

}