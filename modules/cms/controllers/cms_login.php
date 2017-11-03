<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_login extends MY_Controller {
		
    public function __construct() {
    	
        parent::__construct();        

        $this->js[] = array('script' => 'js/jquery-ui.min.js', );

        $this->js[] = 'js/panels.js';
        $this->js[] = 'modules/cms/js/cms.js';
  
        $GLOBALS['_panel_titles'][] = 'ADMIN';
        
   	}
   	
   	function index(){
   		
   		// if no doing here and already logged in, go to admin
   		$do = $this->input->post('do');
   		if (empty($do) && !empty($_SESSION['cms_user']['cms_user_id'])){
   			header('Location: '.$GLOBALS['config']['base_url'].'admin/', true, 302);
			exit();
   		}
   		
        // set page config
        $page_config = array(
        		array('position' => 'main', 'panel' => 'cms_user', 'module' => 'cms', ),
        );
        
        // render panels
        $panel_data = $this->render($page_config);
        
    	$this->output('cms_login', $panel_data);
   		
   	}

}
