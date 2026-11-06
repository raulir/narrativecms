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

	function panel_action($params){
		
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_slug_model');
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_panel_cms_model');
		
		$do = $params['cms_do'] ?? false;
		if ($do == 'reserved_page_draft_put'){

			$layout = trim((string)($params['layout'] ?? ''));
			if ($layout === ''){
				return ['ok' => 0, 'error' => 'Set a layout before adding a panel'];
			}

			$page_class = trim((string)($params['page_class'] ?? ''));
			$slug = trim((string)($params['slug'] ?? ''));
			if (!in_array($page_class, ['list', 'system'], true) || $slug === ''){
				return ['ok' => 0, 'error' => 'Invalid reserved page'];
			}

			$this->cms_page_model->put_reserved_page_draft([
					'page_class' => $page_class,
					'slug' => $slug,
					'list_panel' => trim((string)($params['list_panel'] ?? '')),
					'title' => (string)($params['title'] ?? ''),
					'layout' => $layout,
					'access' => trim((string)($params['access'] ?? '')),
					'cache' => trim((string)($params['cache'] ?? '')),
					'status' => (int)($params['status'] ?? 0),
					'seo_title' => (string)($params['seo_title'] ?? ''),
					'description' => (string)($params['description'] ?? ''),
					'image' => (string)($params['image'] ?? ''),
					'video' => (string)($params['video'] ?? ''),
					'video_id' => (string)($params['video_id'] ?? ''),
					'positions' => ($params['positions'] ?? false),
			]);

			return ['ok' => 1];

		} else if ($do == 'cms_page_delete'){

			$page_id = (int)($params['page_id'] ?? 0);
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

			$page_id = (int)($params['page_id'] ?? 0);
			$status = !empty($params['status']) ? 1 : 0;

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
			$page_id = $params['cms_page_id'] ?? false;
			$language = $params['language'] ?? false;
			$this->load->model('cms/cms_language_model');
			$resolved_language = $this->cms_language_model->resolve_language_id($language, $GLOBALS['language']['languages'] ?? []);
			$language = $resolved_language !== false ? $resolved_language : false;
			
			$data['sort'] = $params['sort'] ?? false;
			$data['title'] = $params['title'] ?? false;
			$data['slug'] = $params['slug'] ?? false;
			$data['access'] = trim((string)($params['access'] ?? ''));
			$data['cache'] = trim((string)($params['cache'] ?? ''));
			
			$data['position'] = $params['position'] ?? false;
			if (empty($data['position'])) {
				$data['position'] = 'main';
			}
			
			// not valid for non-main
			if ($data['position'] == 'main'){
				$data['status'] = $params['status'] ?? false;
				$data['seo_title'] = $params['seo_title'] ?? false;
				$data['description'] = $params['description'] ?? false;
				$data['image'] = $params['image'] ?? false;
				$data['video'] = $params['video'] ?? false;
				$data['video_id'] = $params['video_id'] ?? false;
				$data['layout'] = $params['layout'] ?? false;
				$data['positions'] = $params['positions'] ?? false;
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
				$page_class_post = trim((string)($params['page_class'] ?? ''));
				$list_panel_post = trim((string)($params['list_panel'] ?? ''));
				$reserved_new = (int)($params['reserved_new'] ?? 0);
				if ($reserved_new && in_array($page_class_post, ['list', 'system'], true)){
					$data['page_class'] = $page_class_post;
					$data['list_panel'] = $list_panel_post;
					$data['slug'] = trim((string)($params['slug'] ?? ''));
					return [
							'ok' => 0,
							'error' => 'Add a panel before saving a reserved page',
					];
				}
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
			
			$return = ['ok' => 1, 'cms_page_id' => $page_id];

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

			$block_orders = $params['orders'] ?? false;

			$this->cms_page_panel_cms_model->save_orders($block_orders);

		}
	
	}

}
