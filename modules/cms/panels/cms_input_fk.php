<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_fk extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_input_select.scss');
		
	}
	
	function panel_params($params){
		
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_page_panel_model');
		
		// get fk data
		if (!empty($params['list'])){
			
			if (!empty($params['add_empty'])){
				$params['values'][0] = '-- not specified --';
			}
			
			$list = $this->cms_page_panel_model->get_list($params['list'], ['show' => [0,1]]);

			foreach($list as $item_id => $item){
				$params['values'][$item_id] = $item['title'];
			}
			
			if(empty($params['values'])){
				$params['values'] = ['0' => '-- no values --'];
			}
		
		} else {

			$fk_data = $this->cms_panel_model->get_cms_panel_fk_data($params['panel_structure']);
			
			if (empty($params['add_empty'])){
				if (isset($fk_data[$params['name_clean']][0]) && $fk_data[$params['name_clean']][0] == '-- not specified --'){
					unset($fk_data[$params['name_clean']][0]);
				}
			}

			$params['values'] = !empty($fk_data[$params['target']]) ? $fk_data[$params['target']] : $fk_data[$params['name_clean']];

		}

		$params['name_clean'] = str_replace('/', '_', $params['name_clean']);
		
		return $params;
	
	}

}
