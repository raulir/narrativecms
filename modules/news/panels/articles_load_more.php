<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class articles_load_more extends CI_Controller{
	
	function panel_params($params){
		
		// load next n articles
		$this->load->model('cms/cms_page_panel_model');
		
		$params['articles'] = $this->cms_page_panel_model->get_list('article', ['_start' => $params['start'], '_limit' => $params['increment'], 'type' => explode(',', $params['types']), ]);

		return $params;
		
	}

}
