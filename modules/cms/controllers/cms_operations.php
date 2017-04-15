<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_operations extends MY_Controller {
		
    public function __construct() {
    	
        parent::__construct();        
        $this->css[] = array('script' => 'modules/cms/css/cms.css', 'no_pack' => 1, );
        
       	// $this->js[] = array('script' => 'js/jquery-3.1.0.min.js', 'no_pack' => 1, 'sync' => '', );
        $this->js[] = array('script' => 'js/jquery-ui.min.js', );

        $this->js[] = 'js/preloader.js';
        $this->js[] = 'js/md5.js';
        $this->js[] = 'js/panels.js';

        $this->js[] = 'modules/cms/js/cms.js';
  
        $GLOBALS['_panel_titles'][] = 'ADMIN - '.$GLOBALS['config']['title'];
   	}
	
   	// recreate routes
   	function update_routes(){
   		
   		$this->load->model('cms_slug_model');
   		$this->cms_slug_model->_regenerate_cache();
   		
   		header('Location: '.$_SERVER['REQUEST_URI']);
   		
   	}

}
