<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class webform_admin extends MY_Controller{
	
	function panel_action($params){
	
        if (!empty($params['cms_page_panel_id'])){
        	
        	// get page panel data 
        	$this->load->model('cms_page_panel_model');
        	$page_panel = $this->cms_page_panel_model->get_cms_page_panel($params['cms_page_panel_id']);

        	// diacritics
   			$filename = html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', 
					'$1', $page_panel['title']), ENT_QUOTES, 'UTF-8');
	    	// non alphanumeric
	    	$filename = ' '.preg_replace('/[^a-z0-9]/', '  ', strtolower($filename)).' ';
	    	// common words
			$filename = str_replace(array(' a ', ' an ', ' the ', ), '', $filename);
			// add dashes
			$filename = substr(preg_replace('/[ ]+/', '-', trim($filename)), 0, 40);
        	
			$this->load->model('form_model');
			$this->form_model->file_webform_data($params['cms_page_panel_id'], $filename);
        
        }
	
	}
	
	function panel_params($params){
		
		$this->load->model('form_model');
		$params['webforms'] = $this->form_model->get_webforms();
		
		return $params;
		
	}

}
