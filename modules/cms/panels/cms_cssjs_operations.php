<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_cssjs_operations extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$this->load->model('cms/cms_page_panel_model');
		
		$do = $this->input->post('do');

		if ($do == 'cms_cssjs_save'){

			$panels = $this->input->post('panels');

			// get current config
			$settings_a = $this->cms_page_panel_model->get_cms_page_panels_by(['panel_name' => 'cms/cms_cssjs_settings', 'cms_page_id' => 0, ]);

			if (!count($settings_a)){
				$cms_page_panel_id = $this->cms_page_panel_model->create_cms_page_panel(['panel_name' => 'cms/cms_cssjs_settings', ]);
			} else {
				$cms_page_panel_id = $settings_a[0]['cms_page_panel_id'];
			}

			// update
			$this->cms_page_panel_model->update_cms_page_panel($cms_page_panel_id, ['css' => $panels, ]);
			
			if (file_exists($GLOBALS['config']['base_path'].'cache/cms_cssjs_settings.json')){
				unlink($GLOBALS['config']['base_path'].'cache/cms_cssjs_settings.json');
			}
			 
		}

		return $params;

	}

}
