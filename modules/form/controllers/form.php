<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class form extends MY_Controller {
		
    public function __construct() {
    	
        parent::__construct();        

   		// check if user
   		if(empty($_SESSION['admin_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
   		}
    	
        $this->css[] = array('script' => 'modules/cms/css/cms.css', 'no_pack' => 1, );
        
       	$this->js[] = array('script' => 'js/jquery-3.1.0.min.js', 'no_pack' => 1, 'sync' => '', );
        $this->js[] = array('script' => 'js/jquery-ui.min.js', );
        $this->js[] = array('script' => 'js/tinymce/tinymce.min.js', 'sync' => '', );

        $this->js[] = 'js/preloader.js';
        $this->js[] = 'js/md5.js';
        $this->js[] = 'js/panels.js';

        $this->js[] = 'modules/cms/js/cms.js';
  
        $GLOBALS['_panel_titles'][] = 'ADMIN - '.$GLOBALS['config']['title'];
   		
   	}
   	
   	function admin($cms_page_panel_id = ''){
   		
   		// check if user
   		if(empty($_SESSION['admin_id']) && ($_SESSION['admin_id'] != 1 || $_SESSION['admin_id'] != 2)){
			header('Location: '.$GLOBALS['config']['base_url'].'admin/login/', true, 302);
			exit();
   		}
   		
        // set page config
        $page_config = array(
        		array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
        		array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
   				array(
					'position' => 'main', 
					'panel' => 'webform_admin',
   					'module' => 'form',
					'params' => array('cms_page_panel_id' => $cms_page_panel_id, ),
				),
        );
        
        // render panels
        $panel_data = $this->render($page_config);
        
    	$this->output('admin', $panel_data);
   	
   	}

}
