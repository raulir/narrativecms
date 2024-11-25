<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class searchajax extends CI_Controller{

	function panel_params($params){
		
		$hash = substr(md5($params['term']), 0, 20);
		$filename = $GLOBALS['config']['base_path'].'cache/search_'.$hash.'.json';
		if (file_exists($filename) && (time()-filemtime($filename)) < 600){
			$params['result'] = json_decode(file_get_contents($filename), true);
			return $params;
		}
		
		$this->load->model('cms/cms_search_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_slug_model');
		
		$lists = $this->cms_page_panel_model->get_lists();

		$result = [
				'cms_pages' => [],
		];
		
		if ($params['term'] && strlen($params['term']) >= 3) {
			
			$result = $this->cms_search_model->get_search($params['term'] /*, ['all' => 1, ] */ );
			
		} else {
			
			$params['error_message'] = str_ireplace('{{x}}', '3', $params['no_characters']);
			
		}
		
		$params['result'] = [];

		// add more data
		foreach ($result['cms_pages'] as $page_id => $score){

			$slug = $this->cms_slug_model->get_cms_slug_by_target($page_id);
				
			if (stristr($page_id, '=')){
				
				// list item page
				list($panel_name, $cms_page_panel_id) = explode('=', $page_id);

				$data = $this->cms_page_panel_model->get_cms_page_panel($cms_page_panel_id);

				if (!empty($data['show']) && !empty($slug)){
				
//					if ($data['panel_name'] == 'download/download'){
//						$score += 1;
//					}

					$params['result'][$page_id] = [
							'image' => $data['image'] ?? '',
							'text' => $data['text'] ?? '',
							'heading' => $data['heading'] ?? '',
							'page_id' => $page_id,
							'slug' => $slug,
							'score' => $score,
							'data' => $data,
					];
				}
					
			} else {
				
				// static page
				$page_data = $this->cms_page_model->get_page($page_id);
				
				if (in_array($slug, $lists)){
					$slug = '';
				}
				
				if (!empty($slug) && empty($page_data['status'])){
					$params['result'][$page_id] = [
							'image' => (!empty($page_data['image']) ? $page_data['image'] : ''),
							'text' => 'page',
							'heading' => (!empty($page_data['title']) ? $page_data['title'] : '[ no title ]'),
							'page_id' => $page_id,
							'slug' => $slug,
							'score' => $score,
							'data' => $data,
					];
				}
				
			}
			
		}
		
		// sort by score
		
		function scoresort($a, $b){
			if ($a['score'] < $b['score']){
				return 1;
			} else {
				return -1;
			}
		}
		
		uasort($params['result'], 'scoresort'); 
		
		// cut top 5
		$params['result'] = array_slice($params['result'], 0, 12);
		
		file_put_contents($filename, json_encode($params['result'], JSON_PRETTY_PRINT));
		
		return $params;
		
	}
	
	
}
