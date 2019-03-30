<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class news extends CI_Controller {
		
    public function __construct() {
    	
       	parent::__construct();        
        $this->css[] = 'modules/cms/css/cms.css';

      	$this->js[] = array('script' => 'js/jquery-3.1.0.min.js', 'no_pack' => 1, 'sync' => '', );
        $this->js[] = array('script' => 'js/jquery-ui.min.js', );
        $this->js[] = array('script' => 'js/tinymce/tinymce.min.js', 'sync' => '', );

        $this->js[] = 'js/preloader.js';
        $this->js[] = 'js/md5.js';
        $this->js[] = 'js/panels.js';

        $this->js[] = 'modules/cms/js/cms.js';
        
        $this->title = 'ADMIN - '.$GLOBALS['config']['title'];
        
   	}
	
   	function admin(){

   		// check if user
   		if(empty($_SESSION['admin_id']) && ($_SESSION['admin_id'] != 1 || $_SESSION['admin_id'] != 2)){
			header('Location: '.$GLOBALS['config']['base_url'].'admin/login/', true, 302);
			exit();
   		}

        // set page config
        $page_config = array(
        		array('position' => 'header', 'panel' => 'cms_user', ),
        		array('position' => 'header', 'panel' => 'cms_menu', ),
   				array(
					'position' => 'main',
					'panel' => 'cms_list',
					'params' => array(
						'title' => 'Articles',
						'edit_base' => 'news/admin_article/',
						'filter' => array('panel_name' => 'article', 'page_id' => '999999', ), // what is shown in the list
						'title_field' => 'heading',
						'filter_fields' => array('published' => '-- published --', 'type' => '-- type --', ),
					),
				),
        );
        
        // render panels
        $panel_data = $this->render($page_config);
        
    	$this->output('admin', $panel_data);
   	
   	}

   	function admin_article($block_id = 0){
   		
   		// check if user
   		if(empty($_SESSION['admin_id']) && ($_SESSION['admin_id'] != 1 || $_SESSION['admin_id'] != 2)){
			header('Location: '.$GLOBALS['config']['base_url'].'admin/login/', true, 302);
			exit();
   		}
   		
        // set page config
        $page_config = array(
        		array('position' => 'header', 'panel' => 'cms_user', ),
        		array('position' => 'header', 'panel' => 'cms_menu', ),
   				array(
					'position' => 'main', 
					'panel' => 'admin_block', 
					'params' => array(
						'type' => 'article', 
						'show_delete' => '1',
						'base_url' => 'news/admin/',
						'base_title' => 'Articles',
						'filter' => array('block_id' => $block_id, ),
						'title_field' => 'heading',
						'on_save' => array('model' => 'cms_slug_model', 'function' => 'request_slug', 'params' => array('article=_block_id', '_heading', ), ),
						'on_delete' => array('model' => 'cms_slug_model', 'function' => 'delete_slug', 'params' => array('article='.$block_id, ), ),
					), 
				),
        );
        
        // render panels
        $panel_data = $this->render($page_config);
        
    	$this->output('admin', $panel_data);
   	
   	}

}
