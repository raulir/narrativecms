<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class article_related extends CI_Controller{
	
	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');

		$articles = $this->cms_page_panel_model->get_list('article');

		unset($articles[$params['_cms_page_panel_id']]);
		
		$article_keys = array_rand($articles, 3);

		foreach($article_keys as $article_id){
			$params['articles'][$article_id] = $articles[$article_id];
		}
		
		shuffle($params['articles']);
		
		return $params;

	}

}
