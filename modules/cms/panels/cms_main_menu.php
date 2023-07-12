<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_main_menu extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $this->input->post('do');
		if ($do == 'admin_main_menu_save'){

			$this->load->model('cms/cms_menu_model');

			$menu_id = $this->input->post('menu_id');
			$menu_item_ids = $this->input->post('menu_item_id');
			$modes = $this->input->post('mode');
			$texts = $this->input->post('text');
			$links = $this->input->post('link');
			$new_window = $this->input->post('new_window');
			$hide_from_menu = $this->input->post('hide_from_menu');
				
			if (!is_array($menu_item_ids)){
				$menu_item_ids = array();
			}

			// delete non existent ids
			$existing_menu_item_ids = $this->cms_menu_model->get_menu_items($menu_id);
			foreach($existing_menu_item_ids as $menu_item){
				if (!in_array($menu_item['menu_item_id'], $menu_item_ids)){
					$this->cms_menu_model->delete_menu_item($menu_item['menu_item_id']);
				}
			}
				
			// update remaining
			foreach($menu_item_ids as $sort => $menu_item_id){

				$menu_item = $this->cms_menu_model->get_menu_item($menu_item_id);

				if (!empty($menu_item['menu_item_id'])){
					$this->cms_menu_model->update_menu_item($menu_item_id,
							array('link' => $links[$sort], 'text' => $texts[$sort], 'sort' => $sort,
									'new_window' => $new_window[$sort],
									'hide_from_menu' => $hide_from_menu[$sort],));
				} else {
					$menu_item = $this->cms_menu_model->new_menu_item($menu_id);
					$menu_item['link'] = $links[$sort];
					$menu_item['text'] = $texts[$sort];
					$menu_item['sort'] = $sort;
					$menu_item['mode'] = $modes[$sort];
					$menu_item['new_window'] = $new_window[$sort];
					$menu_item['hide_from_menu'] = $hide_from_menu[$sort];
					$this->cms_menu_model->create_menu_item($menu_item);
				}

			}
				
		}
	}

	function panel_params($params){

		$this->load->model('cms/cms_menu_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_page_panel_model');

		$return['menu_items'] = $this->cms_menu_model->get_menu_items($params['menu_id']);

		foreach($return['menu_items'] as $key => $menu_item){
			$return['menu_items'][$key]['is_submenu'] = count($this->cms_menu_model->get_menu_items($menu_item['menu_item_id']));
				
			$page_slug = false;
				
			// get page id from full link present
			if ($menu_item['link'] == ''){
				$page_slug = '';
				$page_panel_slug = '';
			} elseif (stristr($menu_item['link'], '/')){
				$link_parts = explode('/', $menu_item['link']);
				if (count($link_parts) == 2 && $link_parts[1] === ''){
					$page_slug = $link_parts[0];
					$page_panel_slug = '';
				} elseif (count($link_parts) == 2 && substr($link_parts[1], 0, 1) == '#' ) {
					$page_slug = $link_parts[0];
					$page_panel_slug = trim($link_parts[1], '#');
				}
			}
				
			if ($page_slug !== false){

				$page = $this->cms_page_model->get_page_by_slug($page_slug);
				$return['menu_items'][$key]['page_id'] = !empty($page['page_id']) ? $page['page_id'] : 0;

				if ($page_panel_slug != ''){

					// try to find the page with this panel
					$cms_page_panels = $this->cms_page_panel_model->get_cms_page_panels_by(array('submenu_anchor' => $page_panel_slug, ));
					if (!empty($cms_page_panels[0]['block_id'])){
						$return['menu_items'][$key]['block_id'] = $cms_page_panels[0]['block_id'];
					} else {
						$return['menu_items'][$key]['block_id'] = 0;
					}
						
				}
			}
				
		}

		if ($params['menu_id']){
			$return['menu'] = $this->cms_menu_model->get_menu_item($params['menu_id']);
		}

		$return['pages'] = $this->cms_page_model->get_cms_pages();

		$this->load->model('cms/cms_page_panel_model');
		$return['cms_page_panels'] = $this->cms_menu_model->get_cms_page_panels();
		foreach($return['cms_page_panels'] as $key => $value){
			if ($value['submenu_anchor'] == ''){
				unset($return['cms_page_panels'][$key]);
			}
		}

		return $return;

	}

}
