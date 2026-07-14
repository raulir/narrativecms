<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Ajax_api extends Controller {
	
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
		
		if (!empty($this->params['cms_page_panel_id'])){

			$this->load->model('cms/cms_panel_model');
			$this->load->model('cms/cms_page_panel_model');

			$panel_config = $this->cms_panel_model->get_cms_panel_config($this->params['panel_id']);

			$is_action = !empty($this->params['do']) || !empty($this->params['no_html']);
			$is_cms_admin = !empty($_SESSION['cms_user']['cms_user_id']);
			$allow_ajax_panels = !empty($GLOBALS['config']['allow_ajax_panels']) && $GLOBALS['config']['allow_ajax_panels'] !== '0';

			if ($is_action || $is_cms_admin || !empty($panel_config['list']['ajax_target']) || $allow_ajax_panels){
				$instance = $this->cms_page_panel_model->get_cms_page_panel($this->params['cms_page_panel_id']);
				if (!is_array($instance)){
					print(json_encode(array('result' => array(), 'error' => array('message' => 'Panel not found', ))));
					die();
				}
				$this->params = array_merge($instance, $this->params);
			} else {
				print(json_encode(array('result' => array(), 'error' => array('message' => 'List ajax blocked', ))));
				die();
			}

			if (!$is_action && !$is_cms_admin && empty($this->params['show'])){
				print(json_encode(array('result' => array(), 'error' => array('message' => 'Can\'t show', ))));
				die();
			}

		}
   		
		$data = $this->ajax_panel($this->params['panel_id'], $this->params);

		print(json_encode(['result' => $data]));
   	}
   	
   	function get_panel_anchor(){
   		
   		$this->load->model('cms/cms_page_panel_model');
   		$cms_page_panels = $this->cms_page_panel_model->get_cms_page_panels_by(['submenu_anchor' => $this->params['anchor']]);

		if (count($cms_page_panels)){
	   		
   			$cms_page_panel = reset($cms_page_panels);
   			
   			$data = $this->ajax_panel($cms_page_panel['panel_name'], array_merge($this->input->post(), $cms_page_panel));
   			
   			print(json_encode(['result' => ['html' => $data['_html']]], JSON_PRETTY_PRINT));
   			die();
   			
   		}
   		
   		print(json_encode(array('result' => [], 'error' => ['message' => 'Can\'t show', ])));
   		die();
   		
   	}
    
}
