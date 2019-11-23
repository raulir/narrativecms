<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel_caching extends CI_Controller {

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

		// get target_id caching parameters
		$cms_page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['target_id']);
		$params['cache'] = !empty($cms_page_panel['_cache_time']) ? $cms_page_panel['_cache_time'] : 0;
		$params['no_cache_lists'] = !empty($cms_page_panel['_cache_lists']) ? explode(',', $cms_page_panel['_cache_lists']) : [];

		$global_cache = '';
		if ($GLOBALS['config']['panel_cache'] == 0){
			$global_cache = 'no caching';
		} elseif ($GLOBALS['config']['panel_cache'] < 10000){
			$global_cache = round($GLOBALS['config']['panel_cache']/60) . ' minutes';
		} elseif ($GLOBALS['config']['panel_cache'] > 1000000){
			$global_cache = '3 months';
		} else {
			$global_cache = round($GLOBALS['config']['panel_cache']/3600) . ' hours';
		}

		$params['caching_options'] = array(
				'0' => 'Default ('.$global_cache.')',
				'-1' => 'No caching',
				'180' => '3 minutes',
				'900' => '15 minutes',
				'7200' => '2 hours',
				'86400' => '24 hours'
		);

		// get available lists
		$params['lists'] = $this->cms_page_panel_model->get_lists();

		$params['params'] = $params;
		return $params; // array('params' => $params);

	}

}
