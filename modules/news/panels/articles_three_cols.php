<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class articles_three_cols extends CI_Controller{
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		
		$types = array();
		if (!empty($params['types'])){
			foreach($params['types'] as $type){
				$types[] = $type['type'];
			}
		}
		
		$params['types'] = $types;
		
		// load first n articles
		$params['articles'] = $this->cms_page_panel_model->get_list('article', ['_limit' => $params['limit'], 'type' => $params['types'], ]);
		
		// check if load more
		$articles = $this->cms_page_panel_model->get_list('article', ['type' => $params['types'], ]);
		
		$params['load_more'] = count($articles) > $params['limit'];
				
		return $params;
		
	}

}
