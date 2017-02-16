<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class admin extends MY_Controller {

	public function __construct() {
		 
		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->css[] = array('script' => 'modules/cms/css/cms.css', 'no_pack' => 1, );

		$this->js[] = array('script' => 'js/jquery-3.1.0.min.js', 'no_pack' => 1, 'sync' => '', );
		$this->js[] = array('script' => 'js/jquery-ui.min.js', );

		$this->js[] = 'js/preloader.js';
		$this->js[] = 'js/md5.js';
		$this->js[] = 'js/panels.js';

		$this->js[] = 'modules/cms/js/cms.js';

		$GLOBALS['_panel_titles'][] = 'ADMIN - '.$GLOBALS['config']['title'];
		
	}

	function index(){
		 
		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'cms_welcome', 'module' => 'cms', ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function pages(){
		 
		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'admin_pages', 'module' => 'cms', ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function page($page_id){
		 
		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'cms_page', 'module' => 'cms', 'params' => array('page_id' => $page_id, ), ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function block($block_id, $page_id = 0, $parent_id = 0, $parent_field_name = ''){
		 
		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'admin_block', 'module' => 'cms', 'params' => [
						'block_id' => $block_id,
						'page_id' => $page_id,
						'parent_id' => $parent_id,
						'parent_field_name' => $parent_field_name,
				], ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function menu($menu_id = 0){

		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'cms_main_menu', 'module' => 'cms', 'params' => array('menu_id' => $menu_id, ), ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function menu_main(){
		 
		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'admin_block', 'module' => 'cms', 'params' => array('filter' => array('panel_name' => 'menu_main', ), ), ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function table($table){

		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'cms_table', 'module' => 'cms', 'params' => array('table' => $table, ), ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function keywords(){

		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array(
						'position' => 'main',
						'panel' => 'cms_list',
						'module' => 'cms',
						'params' => array(
								'title' => 'Keywords',
								'edit_base' => 'admin/keyword/',
								'source' => array('model' => 'cms_keyword_model', 'method' => 'get_cms_keywords', ),
								'title_field' => 'cms_keyword_id',
								'id_field' => 'cms_keyword_id',
								'no_sort' => 1,
						),
				),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function cms_list($list_item){

		// get list item params
		$this->load->model('cms_panel_model');
		$item_config = $this->cms_panel_model->get_cms_panel_config($list_item);

		if (!empty($item_config['list'])){

			$params = array(
					'title' => $item_config['list']['list_title'],
					'edit_base' => 'admin/cms_list_item/' . $list_item . '/',
					'filter' => array('panel_name' => $list_item, 'page_id' => [999999,0], ),
			);
				
			if (!empty($item_config['list']['filter_fields'])){
				$params['filter_fields'] = $item_config['list']['filter_fields'];
			}
				
			if (!empty($item_config['list']['title_panel'])){
				$params['title_panel'] = $item_config['list']['title_panel'];
			} else {
				$params['title_field'] = $item_config['list']['title_field'];
			}

			// set page config
			$page_config = array(
					array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
					array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
					array(
							'position' => 'main',
							'panel' => 'cms_list',
							'module' => 'cms',
							'params' => $params,
					),
			);
			 
			// render panels
			$panel_data = $this->render($page_config);
			 
			$this->output('admin', $panel_data);
			 
		}

	}

	function cms_list_item($list_item, $cms_page_panel_id = 0){
		 
		// get list item params
		$this->load->model('cms_panel_model');
		$item_config = $this->cms_panel_model->get_cms_panel_config($list_item);

		if (!empty($item_config['list'])){

			$params = array(
					'type' => $list_item,
					'filter' => array('block_id' => $cms_page_panel_id, ),
					'title_field' => $item_config['list']['title_field'],
					// not working through this anymore -> now checks list item struct, if is link target
					// 'on_save' => array('model' => 'cms_slug_model', 'function' => 'request_slug',
					//		'params' => array($list_item.'=_block_id', '_'.$item_config['list']['title_field'], ), ),
					'on_delete' => array('model' => 'cms_slug_model', 'function' => 'delete_slug',
							'params' => array($list_item.'='.$cms_page_panel_id, ), ),
			);
				
			// set page config
			$page_config = array(
					array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
					array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
					array(
							'position' => 'main',
							'panel' => 'admin_block',
							'module' => 'cms',
							'params' => $params,
					),
			);

			// render panels
			$panel_data = $this->render($page_config);
			 
			$this->output('admin', $panel_data);

		}

	}

	function panel_settings($panel_name, $title = ''){

		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'admin_block', 'module' => 'cms', 'params' => array(
						'filter' => array('panel_name' => $panel_name, ),
						'title' => $title,
				), ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);

	}

	function update($param = ''){

		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'cms_update', 'module' => 'cms', 'params' => array(
						'do' => $param,
				), ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		$this->output('admin', $panel_data);
		 
	}

	function users($param = ''){

		// set page config
		$page_config = array(
				array('position' => 'header', 'panel' => 'cms_user', 'module' => 'cms', ),
				array('position' => 'header', 'panel' => 'cms_menu', 'module' => 'cms', ),
				array('position' => 'main', 'panel' => 'cms/cms_user_settings', ),
		);

		// render panels
		$panel_data = $this->render($page_config);

		// output to layout
		$this->output('admin', $panel_data);
		 
	}

	// deprecated
	function cms_panel_in_panel($cms_page_panel_id, $parent_id = 0, $parent_name = ''){

		$this->block($cms_page_panel_id);

	}

}
