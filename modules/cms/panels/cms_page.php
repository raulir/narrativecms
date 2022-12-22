<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page extends CI_Controller {

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

		$return['block_list'] = array();
		
		$cms_language = !empty($_SESSION['cms_language']) ? $_SESSION['cms_language'] : false;

		if ($params['cms_page_id']){
				
			$return['page'] = $this->cms_page_model->get_page($params['cms_page_id'], $cms_language);
			$blocks = $this->cms_page_panel_model->get_cms_page_panels_by(['cms_page_id' => $params['cms_page_id']]);
				
			foreach($blocks as $block){
				$return['block_list'][] = $block['cms_page_panel_id'];
			}

		} else {
				
			$return['page'] = $this->cms_page_model->new_page();
			$return['page']['position'] = !empty($params['position']) ? $params['position'] : 'main';
			$return['page']['title'] = 'New '.(!empty($params['position']) ? $params['position'] : 'page');
		}

		// is page a list item page?
		$lists = $this->cms_page_panel_model->get_lists();
		$lists_clean = array_map(function($list_item){
			list($m, $b) = explode('/', $list_item);
			return $b;
		}, $lists);
		
		$return['is_list_item'] = in_array($return['page']['slug'], $lists_clean) ? 1 : 0;
		
		// layout
		$return['cms_page_layout'] = !empty($return['page']['layout']) ? $return['page']['layout'] : 
				(!empty($GLOBALS['config']['layout']) ? $GLOBALS['config']['layout'] : 'cms/rem');

		return $return;

	}

}
