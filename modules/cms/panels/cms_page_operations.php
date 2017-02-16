<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_operations extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action(){

		$do = $this->input->post('do');
		if ($do == 'cms_page_delete'){
			 
			$page_id = $this->input->post('page_id');
			 
			$this->load->model('cms_page_model');

			$this->cms_page_model->delete_page($page_id);
			 
		} else if ($do == 'cms_page_save'){
			 
			// collect data
			$page_id = $this->input->post('page_id');
			$data['sort'] = $this->input->post('sort');
			$data['title'] = $this->input->post('title');
			$data['slug'] = $this->input->post('slug');
			$data['description'] = $this->input->post('description');
			$data['image'] = $this->input->post('image');
			$data['layout'] = $this->input->post('layout');
			 
			// save data
			$this->load->model('cms_page_model');
			if($page_id){
				$this->cms_page_model->update_page($page_id, $data);
			} else {
				$page_id = $this->cms_page_model->create_page($data);
			}
			 
			// check for slug
			$this->load->model('cms_slug_model');
			$data['slug'] = $this->cms_slug_model->request_slug($page_id, !empty($data['slug']) ? $data['slug'] : $data['title']);
			$this->cms_page_model->update_page($page_id, array('slug' => $data['slug'], ));
			
			return array(
					'cms_page_id' => $page_id, 
					'slug' => $data['slug'],
			);

		}
	
	}

}
