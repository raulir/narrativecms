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
			 
			$this->load->model('cms/cms_page_cache_model');
			$this->cms_page_cache_model->invalidate_page($page_id);
			$this->cms_page_model->delete_page($page_id);
			 
		} else if ($do == 'cms_page_save'){

			// collect data
			$page_id = $this->input->post('cms_page_id');
			$language = $this->input->post('language');
			$this->load->model('cms/cms_language_model');
			$resolved_language = $this->cms_language_model->resolve_language_id($language, $GLOBALS['language']['languages'] ?? []);
			$language = $resolved_language !== false ? $resolved_language : false;
			
			$data['sort'] = $this->input->post('sort');
			$data['title'] = $this->input->post('title');
			$data['slug'] = $this->input->post('slug');
			$data['access'] = trim($this->input->post('access'));
			$data['cache'] = trim($this->input->post('cache'));
			
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
				$data['video'] = $this->input->post('video');
				$data['video_id'] = $this->input->post('video_id');
				$data['layout'] = $this->input->post('layout');
				$data['positions'] = $this->input->post('positions');
			}
			 
			$old_slug = '';
			if (!empty($page_id)) {
				$old_page = $this->cms_page_model->get_page($page_id);
				$old_slug = !empty($old_page['slug']) ? $old_page['slug'] : '';
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
				if ($old_slug !== '' && $old_slug !== $return['slug']) {
					$this->load->model('cms/cms_page_cache_model');
					$this->cms_page_cache_model->invalidate_slug($old_slug);
				}
			}

			$this->load->model('cms/cms_page_cache_model');
			$this->cms_page_cache_model->invalidate_page($page_id);
			
			return $return;

		} else if ($do == 'cms_page_panel_order'){

			$block_orders = $this->input->post('orders');

			$this->cms_page_panel_model->save_orders($block_orders);

		}
	
	}

}
