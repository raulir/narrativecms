<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_caching extends \Controller {

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

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_panel_cms_model');

		// The block's current caching settings
		$params['target_id'] = (int)($params['target_id'] ?? 0);
		$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['target_id']);
		$params['cache'] = !empty($cms_page_panel['_cache_time']) ? $cms_page_panel['_cache_time'] : 0;
		$params['no_cache_lists'] = !empty($cms_page_panel['_cache_lists']) ? explode(',', $cms_page_panel['_cache_lists']) : [];

		// The same options and lists the save accepts (cms_page_panel_cms_model::check_caching())
		$params['caching_options'] = $this->cms_page_panel_cms_model->get_caching_options();
		$params['lists'] = $this->cms_page_panel_model->get_lists();

		return $params;

	}

}
