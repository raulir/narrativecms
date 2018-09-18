<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_search_operations extends MY_Controller{

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
			 
			$this->load->model('cms_search_model');
			$this->load->model('cms_page_model');
			$this->load->model('cms_slug_model');
			$this->load->model('cms_page_panel_model');
			$this->load->model('cms_panel_model');
			
			$lists = $this->cms_page_panel_model->get_lists();
			
			$return = ['result' => ['pages' => [], 'page_panels' => [], ], ];
				
			$result = $this->cms_search_model->get_search($term, ['all' => 1, ]);
			
			// add more data
			foreach ($result['cms_pages'] as $page_id => $score){

				// list item page
				if (stristr($page_id, '=')){
					
					list($panel_name, $cms_page_panel_id) = explode('=', $page_id);

					// get heading
					$this->load->model('cms_page_panel_model'); // TODO: bug here - on second round this model disappears
					
					$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);

					$title = $this->run_panel_method($panel_name, 'panel_heading', $data);

					$return['result']['pages']['lists'][$page_id] = [
							'title' => $title,
							'page_id' => $page_id,
							'edit_url' => 'cms_page_panel/' . $cms_page_panel_id . '/',
							'slug' => $this->cms_slug_model->get_cms_slug_by_target($page_id),
							'score' => $score,
							'show' => !empty($data['show']) ? 'show' : 'noshow',
					];
						
				} else {
					
					// static page
					
					$page_data = $this->cms_page_model->get_page($page_id);
					$slug = $this->cms_slug_model->get_cms_slug_by_target($page_id);
					
					if (in_array($slug, $lists)){
						$slug = '';
					}
					
					$return['result']['pages']['real'][$page_id] = [
							'title' => (!empty($page_data['title']) ? $page_data['title'] : '[ no title ]'),
							'page_id' => $page_id,
							'edit_url' => 'page/' . $page_id . '/',
							'slug' => $slug,
							'score' => $score,
							'show' => !empty($page_data['status']) ? 'noshow' : 'show',
					];
					
				}
				
			}
			
			foreach($result['cms_page_panels'] as $cms_page_panel_id => $score){
				
				// pages panels
				if (!in_array($result['panel_data'][$cms_page_panel_id]['page_id'], [0,999999])){
					
					$page_data = $this->cms_page_model->get_page($result['panel_data'][$cms_page_panel_id]['page_id']);
				
					$return['result']['page_panels']['pages'][$cms_page_panel_id] = [
							'title' => (!empty($page_data['title']) ? $page_data['title'] : '[ no title ]') . ' &gt; ' . $result['panel_data'][$cms_page_panel_id]['title'],
							'cms_page_panel_id' => $cms_page_panel_id,
							'edit_url' => 'cms_page_panel/'.$cms_page_panel_id.'/',
							'score' => $score,
							'show' => !empty($result['panel_data'][$cms_page_panel_id]['show']) ? 'show' : 'noshow',
					];
				
				} else if (in_array($result['panel_data'][$cms_page_panel_id]['panel_name'], $lists) || !empty($result['panel_data'][$cms_page_panel_id]['parent_id'])) { // list panels
				
					$this->load->model('cms_page_panel_model'); // TODO: bug here - on second round this model disappears
					$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);
					
					// get main heading
					if (!empty($result['panel_data'][$cms_page_panel_id]['parent_id'])){
						$this->load->model('cms_page_panel_model'); // TODO: bug here - on second round this model disappears
						$parent_data = $this->cms_page_panel_model->get_cms_page_panel($result['panel_data'][$cms_page_panel_id]['parent_id']);
						$parent_title = $this->run_panel_method($result['panel_data'][$cms_page_panel_id]['panel_name'], 'panel_heading', $parent_data);
						if (mb_strlen($parent_title) > 25){
							$parent_title = mb_substr($parent_title, 0, 22).'..';
						}
						$parent_title .= ' &gt; ';
						$title = $parent_data['panel_name'] . ' - ' . $parent_title.$data['title'];
					} else {
						$this->load->model('cms_page_panel_model'); // TODO: bug here - on second round this model disappears
						$title = $this->run_panel_method($result['panel_data'][$cms_page_panel_id]['panel_name'], 'panel_heading', $data);
						$title = $result['panel_data'][$cms_page_panel_id]['panel_name'] . ' - ' . $title;
					}
					
								
					$return['result']['page_panels']['lists'][$cms_page_panel_id] = [
							'title' => $title,
							'cms_page_panel_id' => $cms_page_panel_id,
							'edit_url' => 'cms_page_panel/'.$cms_page_panel_id.'/',
							'score' => $score,
							'show' => !empty($result['panel_data'][$cms_page_panel_id]['show']) ? 'show' : 'noshow',
					];
				
				} else {
					
					// check if editable
					$panel_data = $this->cms_panel_model->get_cms_panel_config($result['panel_data'][$cms_page_panel_id]['panel_name']);
					
					if (empty($panel_data['filename'])){
						$edit_url = '';
						$title = $result['panel_data'][$cms_page_panel_id]['panel_name'] . ' - ' . $result['panel_data'][$cms_page_panel_id]['title'];
					} else {
						$edit_url = 'cms_page_panel/'.$cms_page_panel_id.'/';
						$title = $result['panel_data'][$cms_page_panel_id]['title'];
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
			
			// sort and cut everything
			function scoresort($a, $b){
				return $a['score'] < $b['score'];
			}
			
			if (!empty($return['result']['pages']['lists'])) usort($return['result']['pages']['lists'], 'scoresort');
			if (!empty($return['result']['pages']['real'])) usort($return['result']['pages']['real'], 'scoresort');
			if (!empty($return['result']['page_panels']['pages'])) usort($return['result']['page_panels']['pages'], 'scoresort');
			if (!empty($return['result']['page_panels']['lists'])) usort($return['result']['page_panels']['lists'], 'scoresort');
			if (!empty($return['result']['page_panels']['settings'])) usort($return['result']['page_panels']['settings'], 'scoresort');
				
			return $return;
			
		}

	}

}
