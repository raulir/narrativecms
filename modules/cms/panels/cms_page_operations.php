<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_operations extends \Controller {

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
		$this->load->model('cms/cms_page_panel_cms_model');
		
		$do = $this->input->post('do');
		if ($do == 'cms_page_delete'){

			$page_id = (int)$this->input->post('page_id');
			if ($page_id < 1){
				return ['ok' => 0];
			}

			$page = $this->cms_page_model->get_page($page_id);
			if (empty($page['cms_page_id']) || !$this->cms_page_model->page_can_delete($page)){
				return ['ok' => 0, 'error' => 'delete_not_allowed'];
			}

			$this->load->model('cms/cms_page_cache_model');
			$this->cms_page_cache_model->invalidate_page($page_id);
			$ok = $this->cms_page_model->delete_page($page_id);

			return ['ok' => $ok ? 1 : 0];

		} else if ($do == 'cms_page_set_status'){

			$page_id = (int)$this->input->post('page_id');
			$status = !empty($this->input->post('status')) ? 1 : 0;

			if ($page_id < 1){
				return ['ok' => 0];
			}

			$page = $this->cms_page_model->get_page($page_id);
			if (empty($page['cms_page_id'])){
				return ['ok' => 0];
			}

			$page_class = $this->cms_page_model->get_page_class($page);
			if ($page_class === 'system'){
				return ['ok' => 0, 'error' => 'system'];
			}

			$position = !empty($page['position']) ? $page['position'] : 'main';
			if ($position !== 'main' && $position !== ''){
				return ['ok' => 0, 'error' => 'not_main'];
			}

			$this->cms_page_model->update_page($page_id, ['status' => $status]);
			$slug = $this->cms_page_model->update_page_visibility($page_id);

			$this->load->model('cms/cms_page_cache_model');
			$this->cms_page_cache_model->invalidate_page($page_id);

			return [
					'ok' => 1,
					'status' => $status,
					'label' => $status ? 'show' : 'hide',
					'slug' => $slug,
			];

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

			// Preserve list/system classification on save (not posted from form)
			if (!empty($page_id)) {
				$existing = $this->cms_page_model->get_page($page_id);
				if (!empty($existing['page_class'])) {
					$data['page_class'] = $existing['page_class'];
				}
				if (!empty($existing['list_panel'])) {
					$data['list_panel'] = $existing['list_panel'];
				}
				// List/system reserved slugs must not be overwritten by free-text slugify input
				if (($existing['page_class'] ?? '') === 'list' && !empty($existing['list_panel'])) {
					$data['slug'] = $this->cms_page_model->list_template_slug_from_panel($existing['list_panel']);
				}
				if (($existing['page_class'] ?? '') === 'system' && !empty($existing['slug'])) {
					$data['slug'] = $existing['slug'];
				}
			} else {
				// New pages from admin are user pages
				if (empty($data['page_class'])) {
					$data['page_class'] = 'user';
				}
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

			$this->cms_page_panel_cms_model->save_orders($block_orders);

		}
	
	}

}
