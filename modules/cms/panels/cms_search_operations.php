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
				
			$result = $this->cms_search_model->get_search($term, ['all' => 1, ]);
			
			// add more data
			foreach ($result['cms_pages'] as $page_id => $score){
				
				if (stristr($page_id, '=')){
					
					list($panel_name, $cms_page_panel_id) = explode('=', $page_id);
					
					$params['result']['pages'][$page_id] = [
							'title' => $result['panel_data'][$cms_page_panel_id]['title'].':',
							'page_id' => $page_id,
							'edit_url' => 'cms_page_panel/' . $cms_page_panel_id . '/',
							'slug' => $this->cms_slug_model->get_cms_slug_by_target($page_id),
							'score' => $score,
					];
					
				} else {
					
					$page_data = $this->cms_page_model->get_page($page_id);
					
					$params['result']['pages'][$page_id] = [
							'title' => $page_data['title'].':',
							'page_id' => $page_id,
							'edit_url' => 'page/' . $page_id . '/',
							'slug' => $this->cms_slug_model->get_cms_slug_by_target($page_id),
							'score' => $score,
					];
					
				}
				
			}
			
			foreach($result['cms_page_panels'] as $cms_page_panel_id => $score){
				
				$params['result']['page_panels'][$cms_page_panel_id] = [
						'title' => $result['panel_data'][$cms_page_panel_id]['title'].':',
						'cms_page_panel_id' => $cms_page_panel_id,
						'edit_url' => 'cms_page_panel/'.$cms_page_panel_id.'/',
						'score' => $score,
				];
				
			}
			
			return $params;
			
		}

	}

}
