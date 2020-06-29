<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_operations extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action(){
		
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$do = $this->input->post('do');
		if ($do == 'cms_page_delete'){
			 
			$page_id = $this->input->post('page_id');
			 
			$this->cms_page_model->delete_page($page_id);
			 
		} else if ($do == 'cms_page_save'){

			// collect data
			$page_id = $this->input->post('cms_page_id');
			$language = $this->input->post('language');
			if (empty($GLOBALS['language']['languages'][$language])){
				$language = false;
			}
			
			$data['sort'] = $this->input->post('sort');
			$data['title'] = $this->input->post('title');
			$data['slug'] = $this->input->post('slug');
			
			$data['position'] = $this->input->post('position');
			if (empty($data['position'])) {
				$data['position'] = 'main';
			}
			
			// not valid for non-main
			if ($data['position'] == 'main'){
				$data['status'] = $this->input->post('status');
				$data['seo_title'] = $this->input->post('seo_title');
				$data['description'] = $this->input->post('description');
				$data['image'] = $this->input->post('image');
				$data['layout'] = $this->input->post('layout');
				$data['positions'] = $this->input->post('positions');
			}
			 
			// save data
			if(!empty($page_id)){
				$this->cms_page_model->update_page($page_id, $data, $language);
			} else {
				$page_id = $this->cms_page_model->create_page($data);
			}
			
			$return = ['cms_page_id' => $page_id];

			if ($data['position'] == 'main'){
				$return['slug'] = $this->cms_page_model->update_page_visibility($page_id);
			}
			
			return $return;

		} else if ($do == 'cms_page_panel_order'){

			$block_orders = $this->input->post('orders');

			$this->cms_page_panel_model->save_orders($block_orders);

		}
	
	}

}
