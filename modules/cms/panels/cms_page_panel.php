<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
		
		$this->scss[] = ['script' => 'modules/cms/css/cms_input_select.scss', ];
		
	}

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_module_model');
		
		// if preset panel name (panel type) for new panel
		if (!is_numeric($params['cms_page_panel_id'])){
			$params['panel_name'] = str_replace('__', '/', trim($params['cms_page_panel_id']));
			$params['cms_page_panel_id'] = 0;
			$params['cms_page_id'] = 0;
		}
		
		$return = array();

		if (!empty($params['breadcrumb'])){

			$return['breadcrumb'] = $params['breadcrumb'];

		} else {

			if (!empty($params['base_url'])){
				$return['base_url'] = $params['base_url'];
			}

			if (!empty($params['base_title'])){
				$return['base_title'] = $params['base_title'];
			}

		}

		if (!empty($params['title_field'])){
			$return['title_field'] = $params['title_field'];
		}

		if (!empty($params['_mode'])){
			$return['_mode'] = $params['_mode'];
		}

		// if filter, then get by filter
		if (isset($params['filter'])){
			
			$blocks = $this->cms_page_panel_model->get_cms_page_panels_by($params['filter']);
			
			if (!empty($blocks[0])){
			
				$return['block'] = $blocks[0];
				$return['block']['panel_params'] = $blocks[0];
					
			}
			
		}
		
		// if no filtered block returned but has panel name
		if (empty($return['block']['cms_page_panel_id']) && isset($params['panel_name'])){
			
			// new block of type
			$return['block'] = $this->cms_page_panel_model->new_cms_page_panel();
			$return['block']['panel_params'] = array();
			$return['block']['cms_page_id'] = $params['cms_page_id'];
			
			$return['block']['panel_name'] = $params['panel_name'];
			$return['block']['title'] = 'New ' . $params['panel_name'];
			
			// get new sort too as this is should be real block
			$return['block']['sort'] = $this->cms_page_panel_model->get_max_cms_page_panel_id($params['panel_name']) + 1;

		}

		// if block id, get by this
		if (empty($return['block']) && !empty($params['cms_page_panel_id'])){

			$return['block'] = $this->cms_page_panel_model->get_cms_page_panel($params['cms_page_panel_id']);

		}

		// if still empty, create a new empty
		if (empty($return['block'])) {

			if (!empty($params['parent_id'])){ // panel in panel

				$return['block'] = $this->cms_page_panel_model->new_cms_page_panel();
				$return['block']['panel_params'] = array();
				$return['block']['cms_page_id'] = 0;
				$return['block']['parent_id'] = $params['parent_id'];

			} else {
					
				$return['block'] = $this->cms_page_panel_model->new_cms_page_panel();
				$return['block']['panel_params'] = array();
				$return['block']['cms_page_id'] = $params['cms_page_id'];

			}

		}

		// no page page_id -> 0
		if ($return['block']['cms_page_id'] == 999999) $return['block']['cms_page_id'] = 0;

		if (!empty($return['block']['cms_page_id'])){
			$return['cms_page'] = $this->cms_page_model->get_page($return['block']['cms_page_id']);
			$return['cms_page_id'] = $return['cms_page']['cms_page_id'];
		} else {
			$return['_admin_title'] = $return['block']['title'];
			$return['independent_block'] = 1;
		}

		// this is where panel definition comes from
		$return['block']['panel_definition'] = $return['block']['panel_name'];

		$return['panel_types'] = [];
		$return['panel_replaced'] = [];

		foreach($GLOBALS['config']['modules'] as $module){
			
			$config = $this->cms_module_model->get_module_config($module);

			if (!empty($config['panels'])){
				foreach($config['panels'] as $panel){

					// add panel type to the dropdown of panel types
					$panel_id = $module.'/'.$panel['id'];
					if (!in_array($panel_id, $return['panel_replaced'])){
						
						$return['panel_types'][$panel_id] = ucfirst($module) . ' / ' . $panel['name'];
						
						// if showing the panel type hides some other panel type, like when extending the panel for project
						if (!empty($panel['hides'])){
							if (!empty($return['panel_types'][$panel['hides']])){
								unset($return['panel_types'][$panel['hides']]);
							}
							$return['panel_replaced'][$panel['hides']] = $panel['hides'];
						}
						
					}

					// definition for this panel comes from elsewhere
					if (($panel_id == $return['block']['panel_name'] || $panel_id == $module . '/' . $return['block']['panel_name']) && empty($return['independent_block']) && !empty($panel['definition'])){
						$return['block']['panel_definition'] = stristr($panel['definition'], '/') ? $panel['definition'] : $module.'/'.$panel['definition'];
					}

				}
			}
		}
		
		// check if panel is list item on the same named page
		$panel_definition = $this->cms_panel_model->get_cms_panel_config($return['block']['panel_definition']);
// print_r($panel_definition);
		if (!empty($panel_definition['list']) && !empty($return['cms_page_id']) && $return['block']['panel_name'] == $panel_definition['module'].'/'.$return['cms_page']['slug']){
			if (empty($panel_definition['settings'])){
				$panel_structure = [];
			} else {
				$panel_structure = $panel_definition['settings'];
			}
		} else {
			
			
			if (!empty($return['cms_page_id']) || !empty($return['block']['cms_page_id']) || !empty($return['block']['sort']) || !empty($return['block']['parent_id'])){

				$panel_structure = !empty($panel_definition['item']) ? $panel_definition['item'] : [];
			
			} else {
				if (!empty($panel_definition['settings'])){
					$panel_structure = $panel_definition['settings'];
				} else if (!empty($panel_definition['item'])){
					$panel_structure = $panel_definition['item'];
				} else {
					$panel_structure = [];
				}
			}
		}
		
		// list items with template selector
		if (!empty($panel_definition['list']['templates'])){
			$return['list_templates'] = [];
			foreach($panel_definition['list']['templates'] as $page_slug => $list_template_name){
				$list_template_page = $this->cms_page_model->get_page_by_slug($page_slug); 
				$return['list_templates'][$list_template_page['cms_page_id']] = $list_template_name;
			}
		}
		
		$return['panel_params_structure'] = $panel_structure; // $this->cms_panel_model->get_cms_panel_definition($return['block']['panel_definition']);

		// print_r($return);

		if ((empty($return['independent_block']) || !empty($return['block']['parent_id'])) && $return['block']['panel_name'] === ''){

			$return['shortcuts'] = array();
			// get all possible panels on pages
			$pages = $this->cms_page_model->get_cms_pages();

			foreach($pages as $page){
				$blocks = $this->cms_page_panel_model->get_cms_page_panels_by(array('cms_page_id' => $page['cms_page_id'], ));
				foreach ($blocks as $block){
					if ($block['title'] !== ''){
						$return['shortcuts'][$block['cms_page_panel_id']] = $page['title'].' > '.$block['title'];
					}
				}
			}

		}

		if (empty($return['block']['parent_id'])){
			$return['block']['parent_id'] = !empty($params['parent_id']) ? $params['parent_id'] : 0;
		}

		if (!empty($return['block']['parent_id']) && !empty($params['parent_field_name'])){

			// if has parent, check parent config - get parent type
			$parent = $this->cms_page_panel_model->get_cms_page_panel($return['block']['parent_id']);
			$parent_structure = $this->cms_panel_model->get_cms_panel_definition($parent['panel_name']);

			foreach($parent_structure as $key => $field){
				if ($field['name'] == $params['parent_field_name']){

					$params['allowed_panels'] = $field['panels'];

				}
			}

			$return['parent_field_name'] = $params['parent_field_name'];

		}

		if (!empty($params['allowed_panels'])){
			$allowed_panels = explode(',', $params['allowed_panels']);

			foreach ($return['panel_types'] as $key => $value){

				$allowed = false;

				if (in_array($key, $allowed_panels)){
					$allowed =  true;
				}

				if (stristr($key, '/')){
					list($kmodule, $kpanel) = explode('/', $key);
					if (in_array($kpanel, $allowed_panels)){
						$allowed =  true;
					}
				}

				if (!$allowed){
					unset($return['panel_types'][$key]);
				}

			}
		}

		asort($return['panel_types']);

		return $return;

	}

}