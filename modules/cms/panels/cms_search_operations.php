<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_search_operations extends \Controller {

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

		if ($do == 'cms_search'){
			
			$term = $this->input->post('term');
			 
			$this->load->model('cms/cms_search_model');
			$this->load->model('cms/cms_page_model');
			$this->load->model('cms/cms_slug_model');
			$this->load->model('cms/cms_page_panel_model');
			$this->load->model('cms/cms_panel_model');
			
			$lists = $this->cms_page_panel_model->get_lists();
			
			$return = ['result' => ['pages' => [], 'page_panels' => [], ], ];
				
			$result = $this->cms_search_model->get_search($term, ['all' => 1, ]);
			if (!is_array($result)){
				$result = [];
			}
			$cms_pages = $result['cms_pages'] ?? [];
			$cms_page_panels = $result['cms_page_panels'] ?? [];
			$panel_data_map = $result['panel_data'] ?? [];

			// add more data
			foreach ($cms_pages as $page_id => $score){

				// list item page
				if (stristr((string)$page_id, '=')){

					$parts = explode('=', $page_id, 2);
					$cms_page_panel_id = (int)($parts[1] ?? 0);
					if ($cms_page_panel_id < 1){
						continue;
					}

					$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
					if (empty($data) || !is_array($data)){
						continue;
					}

					$return['result']['pages']['lists'][$page_id] = [
							'title' => $data['title'] ?? '[ no title ]',
							'page_id' => $page_id,
							'edit_url' => 'cms_page_panel/'.$cms_page_panel_id.'/',
							'slug' => $this->cms_slug_model->get_cms_slug_by_target($page_id),
							'score' => $score,
							'show' => !empty($data['show']) ? 'show' : 'noshow',
					];

				} else {

					// static page
					$page_data = $this->cms_page_model->get_page($page_id);
					if (empty($page_data) || !is_array($page_data)){
						continue;
					}
					$slug = $this->cms_slug_model->get_cms_slug_by_target($page_id);

					if (in_array($slug, $lists)){
						$slug = '';
					}

					$return['result']['pages']['real'][$page_id] = [
							'title' => (!empty($page_data['title']) ? $page_data['title'] : '[ no title ]'),
							'page_id' => $page_id,
							'edit_url' => 'page/'.$page_id.'/',
							'slug' => $slug,
							'score' => $score,
							'show' => !empty($page_data['status']) ? 'noshow' : 'show',
					];

				}

			}

			foreach($cms_page_panels as $cms_page_panel_id => $score){

				$pdata = $panel_data_map[$cms_page_panel_id] ?? null;
				if (empty($pdata) || !is_array($pdata) || !array_key_exists('cms_page_id', $pdata)){
					continue;
				}

				// pages panels
				if ($pdata['cms_page_id'] != 0){

					$page_data = $this->cms_page_model->get_page($pdata['cms_page_id']);
					$page_title = (!empty($page_data['title']) ? $page_data['title'] : '[ no title ]');

					$return['result']['page_panels']['pages'][$cms_page_panel_id] = [
							'title' => $page_title.' &gt; '.($pdata['title'] ?? ''),
							'cms_page_panel_id' => $cms_page_panel_id,
							'edit_url' => 'cms_page_panel/'.$cms_page_panel_id.'/',
							'score' => $score,
							'show' => !empty($pdata['show']) ? 'show' : 'noshow',
					];

				} else if (in_array($pdata['panel_name'], $lists) || !empty($pdata['parent_id'])) { // list panels

					$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
					if (empty($data) || !is_array($data)){
						continue;
					}

					if (!empty($pdata['parent_id'])){

						$parent_data = $this->cms_page_panel_model->get_cms_page_panel($pdata['parent_id']);
						if (empty($parent_data) || !is_array($parent_data)){
							continue;
						}
						$parent_title = $parent_data['title'] ?? '';
						if (mb_strlen($parent_title) > 25){
							$parent_title = mb_substr($parent_title, 0, 22).'..';
						}
						$parent_title .= ' &gt; ';
						$title = ($parent_data['panel_name'] ?? '').' - '.$parent_title.($data['title'] ?? '');

					} else {

						$title = ($pdata['panel_name'] ?? '').' - '.($data['title'] ?? '');

					}

					$return['result']['page_panels']['lists'][$cms_page_panel_id] = [
							'title' => $title,
							'cms_page_panel_id' => $cms_page_panel_id,
							'edit_url' => 'cms_page_panel/'.$cms_page_panel_id.'/',
							'score' => $score,
							'show' => !empty($pdata['show']) ? 'show' : 'noshow',
					];

				} else {

					// check if editable
					$panel_config = $this->cms_panel_model->get_cms_panel_config($pdata['panel_name']);

					if (!$pdata['sort']){
						$edit_url = 'panel_settings/'.str_replace('/', '__', $pdata['panel_name']);
						$title = !empty($pdata['title']) ? $pdata['title'] :
								$pdata['panel_name'].' settings';
					} else if (empty($panel_config['filename'])){
						$edit_url = '';
						$title = ($pdata['panel_name'] ?? '').' - '.($pdata['title'] ?? '');
					} else {
						$edit_url = 'cms_page_panel/'.$cms_page_panel_id.'/';
						$title = $pdata['title'] ?? '';
					}

					$return['result']['page_panels']['settings'][$cms_page_panel_id] = [
							'title' => $title,
							'cms_page_panel_id' => $cms_page_panel_id,
							'edit_url' => $edit_url,
							'score' => $score,
							'show' => 'show', // always show
					];

				}

			}

			// Higher score first (PHP 8-friendly int return)
			$scoresort = function($a, $b){
				return ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0));
			};

			if (!empty($return['result']['pages']['lists'])){
				usort($return['result']['pages']['lists'], $scoresort);
			}
			if (!empty($return['result']['pages']['real'])){
				usort($return['result']['pages']['real'], $scoresort);
			}
			if (!empty($return['result']['page_panels']['pages'])){
				usort($return['result']['page_panels']['pages'], $scoresort);
			}
			if (!empty($return['result']['page_panels']['lists'])){
				usort($return['result']['page_panels']['lists'], $scoresort);
			}
			if (!empty($return['result']['page_panels']['settings'])){
				usort($return['result']['page_panels']['settings'], $scoresort);
			}

			return $return;
			
		}

	}

}
