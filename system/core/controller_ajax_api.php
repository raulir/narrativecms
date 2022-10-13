<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax_api extends CI_Controller {
	
    public function __construct() {
    	
        parent::__construct();
        $this->params = $this->input->post();
   	}
   	
   	function get_panel(){
   		if (empty($this->params['panel_id'])){
    		print(json_encode(array('result' => array(), 'error' => array('message' => 'Missing panel_id', ))));
    		die();
   		}

   		if (extension_loaded('newrelic')) {
    		
			newrelic_set_appname(trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), ' '.$GLOBALS['config']['site_title_delimiter']));
   			newrelic_name_transaction('/ajax_api/'.$this->params['panel_id']);

		}
		
		// if list item, load data
		if (!empty($this->params['cms_page_panel_id']) && empty($this->params['no_html']) && empty($this->params['do'])){
			
			$this->load->model('cms/cms_panel_model');
			$this->load->model('cms/cms_page_panel_model');
			
			$panel_config = $this->cms_panel_model->get_cms_panel_config($this->params['panel_id']);
			
			if (!empty($panel_config['list']['ajax_target']) || $GLOBALS['config']['allow_ajax_panels']){
				$this->params = array_merge($this->cms_page_panel_model->get_cms_page_panel($this->params['cms_page_panel_id']), $this->params);
			} else {
				print(json_encode(array('result' => array(), 'error' => array('message' => 'List ajax blocked', ))));
				die();
			}
			
			if (empty($this->params['show'])){
				print(json_encode(array('result' => array(), 'error' => array('message' => 'Can\'t show', ))));
				die();
			}
			
		}
   		
		$data = $this->ajax_panel($this->params['panel_id'], $this->params);

		print(json_encode(['result' => $data]));
   	}
    
}
