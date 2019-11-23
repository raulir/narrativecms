<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class feed extends CI_Controller {
		
	public function __construct() {
			
		parent::__construct();
	
		add_css(array('script' => 'modules/cms/css/cms.scss', 'no_pack' => 1, ));
	
		$GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/jquery/jquery-ui.min.js', );
		
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_preloader.js';
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms.js';
	
		$GLOBALS['_panel_titles'][] = 'ADMIN';
		$GLOBALS['_panel_titles'][] = 'FEED';
		
	}

   	function dashboard(){

   		// check if user
   		if(empty($_SESSION['cms_user']['cms_user_id'])){
   			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
   			exit();
   		}
   		
   		add_css('modules/feed/css/feed.scss');

        // set page config
        $page_config = array(
        		array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
        		array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
   				array(
					'position' => 'main',
					'panel' => 'cms_list',
   					'module' => 'cms',
					'params' => array(
						'title' => 'Feed dashboard',
						'hide_new' => 1,
						'extra_buttons' => array('feed_dashboard_refresh', ),
						'edit_base' => 'admin/cms_page_panel/',
						'filter' => array('panel_name' => ['feed','feed/feed'], 'page_id' => ['999999','0'], ), // what is shown in the list
						'title_panel' => 'feed_dashboard_item',
						'filter_fields' => array('show' => '-- show --', 'source' => '-- source --', ),
						'extra_class' => 'feed_list_container',
						'limit' => 15,
					),
				),
        );

        // render panels
        $panel_data = $this->render($page_config);

        $this->output('cms/admin', 'feed/dashboard', $panel_data);
   	
   	}
   	
   	function instagram_users(){
   		
   		// check if user
   		if(empty($_SESSION['cms_user']['cms_user_id'])){
   			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
   			exit();
   		}
   		 
		add_css('modules/feed/css/feed.scss');

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
        
    	$this->output('cms/admin', 'feed/instagram_users', $panel_data);
   	
   	}

	// TODO: to panel
   	function cron(){
   		
   		if (extension_loaded('newrelic')) {
   			
			newrelic_set_appname($GLOBALS['config']['title']);
			newrelic_ignore_transaction();
		
		}

   		$this->load->model('feed/feed_model');
   		
   		$stats = $this->feed_model->refresh_feeds();

   		print(json_encode($stats));
   		
   	}
   	
   	function cleanup(){
   		
   		if (extension_loaded('newrelic')) {
			newrelic_ignore_transaction();
		}

   		$this->load->model('feed/feed_model');
   		
   		$this->feed_model->clean_feeds();
   		
   	}

}
