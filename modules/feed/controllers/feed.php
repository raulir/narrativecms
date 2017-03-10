<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed extends MY_Controller {
		
	public function __construct() {
			
		parent::__construct();
	
		$this->css[] = array('script' => 'modules/cms/css/cms.css', 'no_pack' => 1, );
	
		$this->js[] = array('script' => 'js/jquery-3.1.0.min.js', 'no_pack' => 1, 'sync' => '', );
		$this->js[] = array('script' => 'js/jquery-ui.min.js', );
	
		$this->js[] = 'js/preloader.js';
		$this->js[] = 'js/md5.js';
		$this->js[] = 'js/panels.js';
	
		$this->js[] = 'modules/cms/js/cms.js';
	
		$GLOBALS['_panel_titles'][] = 'ADMIN - '.$GLOBALS['config']['title'];
	
	}

   	function dashboard(){

   		// check if user
   		if(empty($_SESSION['cms_user']['cms_user_id'])){
   			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
   			exit();
   		}
   		
   		$this->css[] = array(
				'script' => 'modules/feed/css/feed.css',
		);

        // set page config
        $page_config = array(
        		array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
        		array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
   				array(
					'position' => 'main',
					'panel' => 'cms_list',
   					'module' => 'cms',
					'params' => array(
						'title' => 'Feed',
						'hide_new' => 1,
						'extra_buttons' => array('feed_dashboard_refresh', ),
						'edit_base' => 'feed/admin_feed/',
						'filter' => array('panel_name' => 'feed', 'page_id' => '999999', ), // what is shown in the list
						'title_panel' => 'feed_dashboard_item',
						'filter_fields' => array('show' => '-- show --', 'source' => '-- source --', ),
						'extra_class' => 'feed_list_container',
						'limit' => 15,
					),
				),
        );
        
        // render panels
        $panel_data = $this->render($page_config);
        
    	$this->output('admin', $panel_data);
   	
   	}
   	
   	function instagram_users(){
   		
   		// check if user
   		if(empty($_SESSION['cms_user']['cms_user_id'])){
   			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
   			exit();
   		}
   		 
		$this->css[] = array(
				'script' => 'modules/feed/css/feed.css',
		);

        // set page config
        $page_config = array(
        		array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
        		array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
   				array(
					'position' => 'main',
					'panel' => 'feed_instagram_users',
   					'module' => 'feed',
					'params' => array(
						'title' => 'Authorised Instagram users',
					),
				),
        );
        
        // render panels
        $panel_data = $this->render($page_config);
        
    	$this->output('admin', $panel_data);
   	
   	}
   	
   	/**
   	 * admin social feed item
   	 */
   	function admin_feed($block_id = 0){
   		
   		// check if user
   		if(empty($_SESSION['cms_user']['cms_user_id'])){
   			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
   			exit();
   		}
   		 
        // set page config
        $page_config = array(
        		array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
        		array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
   				array(
					'position' => 'main', 
					'panel' => 'admin_block',
   					'module' => 'cms',
					'params' => array(
						'type' => 'feed', 
						'show_delete' => '1',
						'base_url' => 'feed/dashboard/',
						'base_title' => 'Feed',
						'filter' => array('block_id' => $block_id, ),
						'title_field' => 'heading',
//	mark moderated					'on_save' => array('model' => 'cms_slug_model', 'function' => 'request_slug', 
// 'params' => array('article=_block_id', '_heading', ), ),
					), 
				),
        );
        
        // render panels
        $panel_data = $this->render($page_config);
        
    	$this->output('admin', $panel_data);
   	
   	}

	// TODO: to panel
   	function cron(){
   		
   		if (extension_loaded('newrelic')) {
   			
			newrelic_set_appname($GLOBALS['config']['title']);
			newrelic_ignore_transaction();
		
		}

   		$this->load->model('feed_model');
   		
   		$stats = $this->feed_model->refresh_feeds();

   		print(json_encode($stats));
   		
   	}
   	
   	function cleanup(){
   		
   		if (extension_loaded('newrelic')) {
			newrelic_ignore_transaction();
		}

   		$this->load->model('feed_model');
   		
   		$this->feed_model->clean_feeds();
   		
   	}

}
