<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_page_panel extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
		
		// for type select
		add_css('cms/cms_input_select.scss');

	}

	function panel_params($params){
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_page_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_module_model');
		$this->load->model('cms/cms_user_model');
		$this->load->model('cms/cms_language_model');
		
		$return = [];
		
		// set up new page panel
		$params['target_type'] = $this->input->post('target_type');
		$params['panel_name'] = $this->input->post('panel_name');

		// if page
		if (empty($params['cms_page_panel_id']) && empty($params['cms_page_id']) && $params['target_type'] == 'page'){
			
			$params['cms_page_id'] = $this->input->post('target_id');
			$params['cms_page_panel_id'] = 0;

			list($params['module'], $params['module_panel_name']) = explode('/', $params['panel_name']);
			
		} else if (empty($params['cms_page_panel_id']) && empty($params['cms_page_id']) && $params['target_type'] == 'panel'){

			$params['parent_id'] = $this->input->post('target_id');
			$params['parent_input_name'] = $this->input->post('target_input_name');
			
			$return['parent_name'] = $params['parent_input_name'];
			
			$params['cms_page_panel_id'] = 0;
			$params['cms_page_id'] = 0;

		}

		// if preset panel name (panel type) for new panel
		if (!is_numeric($params['cms_page_panel_id'])){
			$params['panel_name'] = str_replace('__', '/', trim($params['cms_page_panel_id']));
			$params['cms_page_panel_id'] = 0;
			$params['cms_page_id'] = 0;
		} else if (!empty($params['cms_page_panel_id'])){
			$return['block'] = $this->cms_page_panel_model->get_cms_page_panel($params['cms_page_panel_id'], $this->cms_language_model->get_cms_language());
		}

		if (!empty($params['base_url'])){
			$return['base_url'] = $params['base_url'];
		}

		if (!empty($params['base_title'])){
			$return['base_title'] = $params['base_title'];
		}

		if (!empty($params['title_field'])){
			$return['title_field'] = $params['title_field'];
		}

		if (!empty($params['_mode'])){
			$return['_mode'] = $params['_mode'];
		}

		// if no filtered block returned but has panel name
		if (empty($return['block']['cms_page_panel_id']) && isset($params['panel_name'])){
			
			$return['block'] = $this->cms_page_panel_model->new_cms_page_panel();
			
			// new panel name
			$title = ucfirst(trim(!empty($params['module_panel_name']) ? $params['module_panel_name'] : $params['panel_name']));
			
			$title_needed = true;
			$i = 0;
				
			while ($title_needed){
			
				if ($i == 0){
					$new_title = $title;
				} else {
					$new_title = $title . ' (' . $i . ')';
				}
			
				$i++;
			
				$page_panels = $this->cms_page_panel_model->get_cms_page_panels_by(['title' => $new_title, ]);
			
				if (!count($page_panels)){
					$title_needed = false;
				}
			
			}
				
			$return['block']['title'] = $new_title;
			
			// new block of type
			$return['block']['panel_params'] = array();
			$return['block']['cms_page_id'] = 0; // $params['cms_page_id'];
			
			if ($params['target_type'] == 'page'){
				$return['block']['cms_page_id'] = $params['cms_page_id'];
			}
			
			$return['block']['panel_name'] = $params['panel_name'];
			
			// get new sort too as this is should be real block
			$return['block']['sort'] = $this->cms_page_panel_model->get_max_cms_page_panel_id($params['panel_name']) + 1;

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
		
		// check if panel is list item on the same named page
		$panel_definition = $this->cms_panel_model->get_cms_panel_config($return['block']['panel_definition']);

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
				if (!empty($list_template_page['cms_page_id'])){
					$return['list_templates'][$list_template_page['cms_page_id']] = $list_template_name;
				}
			}
		}
		
		$return['panel_params_structure'] = $panel_structure; // $this->cms_panel_model->get_cms_panel_definition($return['block']['panel_definition']);

		if (empty($return['block']['parent_id'])){
			$return['block']['parent_id'] = !empty($params['parent_id']) ? $params['parent_id'] : 0;
		}
		
		// creation and update
		if (!empty($return['block']['create_cms_user_id'])){
			$return['block']['create_user'] = $this->cms_user_model->get_cms_user($return['block']['create_cms_user_id']);
		}
		if (empty($return['block']['create_user'])){
			$return['block']['create_user'] = [];
		}
		if (!empty($return['block']['update_cms_user_id'])){
			$return['block']['update_user'] = $this->cms_user_model->get_cms_user($return['block']['update_cms_user_id']);
		}
		if (empty($return['block']['update_user'])){
			$return['block']['update_user'] = [];
		}
		
		return $return;

	}

}