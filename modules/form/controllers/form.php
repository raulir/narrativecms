<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class form extends CI_Controller {
		
    public function __construct() {
    	
        parent::__construct();        

    	// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
        
		$this->js[] = array('script' => 'modules/cms/js/jquery/jquery-ui.min.js', );
        
        $this->js[] = 'modules/cms/js/cms.js';
  
        $GLOBALS['_panel_titles'][] = 'ADMIN';
        $GLOBALS['_panel_titles'][] = 'FORM';
        
   	}
   	
   	function admin($cms_page_panel_id = ''){
   		
        // set page config
        $page_config = array(
        		array('position' => 'header', 'panel' => 'cms/cms_user', 'module' => 'cms', ),
        		array('position' => 'header', 'panel' => 'cms/cms_menu', 'module' => 'cms', ),
   				array(
					'position' => 'main', 
					'panel' => 'form/form_admin',
   					'module' => 'form',
					'params' => ['cms_page_panel_id' => $cms_page_panel_id, ],
				),
        );
        
        // render panels
        $panel_data = $this->render($page_config);
        
    	$this->output('cms/admin', 'form/admin', $panel_data);
   	
   	}
   	
   	function grid($cms_page_panel_id){
   		
   		// set page config
   		$page_config = array(
   				array('position' => 'header', 'panel' => 'cms/cms_user', 'module' => 'cms', ),
   				array('position' => 'header', 'panel' => 'cms/cms_menu', 'module' => 'cms', ),
   				array(
   						'position' => 'main',
   						'panel' => 'form/form_grid',
   						'module' => 'form',
   						'params' => ['cms_page_panel_id' => $cms_page_panel_id, ],
   				),
   		);
   		
   		// render panels
   		$panel_data = $this->render($page_config);
   		
   		$this->output('cms/admin', 'form/admin', $panel_data);
   			
   	}

}
