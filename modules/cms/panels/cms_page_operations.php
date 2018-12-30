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
		
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_page_panel_model');
		
		$do = $this->input->post('do');
		if ($do == 'cms_page_delete'){
			 
			$page_id = $this->input->post('page_id');
			 
			$this->cms_page_model->delete_page($page_id);
			 
		} else if ($do == 'cms_page_save'){
			 
			// collect data
			$page_id = $this->input->post('page_id');
			$language = $this->input->post('language');
			if (empty($GLOBALS['language']['languages'][$language])){
				$language = false;
			}
			
			$data['sort'] = $this->input->post('sort');
			$data['title'] = $this->input->post('title');
			$data['slug'] = $this->input->post('slug');
			$data['status'] = $this->input->post('status');
			$data['seo_title'] = $this->input->post('seo_title');
			$data['description'] = $this->input->post('description');
			$data['image'] = $this->input->post('image');
			$data['layout'] = $this->input->post('layout');
			 
			// save data
			if($page_id){
				$this->cms_page_model->update_page($page_id, $data, $language);
			} else {
				$page_id = $this->cms_page_model->create_page($data);
			}

			// get slug for page
			$slug = $this->cms_slug_model->generate_page_slug($page_id, $data['slug']);

			// if page is list item page
			$lists = $this->cms_page_panel_model->get_lists();
			$lists_clean = array_map(function($list_item){
				list($m, $b) = explode('/', $list_item);
				return $b;
			}, $lists);
			
			$is_list_item = in_array($slug, $lists_clean);
			
			// get number of panels on page
			$panels = $this->cms_page_panel_model->get_cms_page_panels_by(['cms_page_id' => $page_id, 'show' => 1, ]);
			$number_panels = count($panels);

			if (empty($data['status']) && !$is_list_item && $number_panels > 0){
				
				// normal active slug
				$this->cms_slug_model->set_page_slug($page_id, $slug, 0);
				
			} else {
			
				// hidden slug
				$this->cms_slug_model->set_page_slug($page_id, $slug, 1);
				
			}
			
			$this->cms_page_model->update_page($page_id, ['slug' => $slug]);
			
			return [
					'cms_page_id' => $page_id, 
					'slug' => $slug,
			];

		}
	
	}

}
