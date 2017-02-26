<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class admin_block extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$this->load->model('cms_page_panel_model');
		$this->load->model('cms_page_model');
		$this->load->model('cms_panel_model');

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
			} else {

				// new block of type
				$return['block'] = $this->cms_page_panel_model->new_block();
				$return['block']['panel_params'] = array();
				$return['block']['page_id'] = 0;
				if (!empty($params['type'])){
					$return['block']['panel_name'] = $params['type'];
					$return['block']['title'] = 'New ' . $params['type'];
				} else if(!empty($params['filter']['panel_name'])){
					$return['block']['panel_name'] = $params['filter']['panel_name'];
					$return['block']['title'] = !empty($params['title']) ? $params['title'] : $params['filter']['panel_name'];
				}

			}

		}

		// if block id, get by this
		if (empty($return['block']) && !empty($params['block_id'])){

			$return['block'] = $this->cms_page_panel_model->get_block($params['block_id']);

		}

		// if still empty, create a new empty
		if (empty($return['block'])) {

			if (!empty($params['parent_id'])){ // panel in panel

				$return['block'] = $this->cms_page_panel_model->new_block();
				$return['block']['panel_params'] = array();
				$return['block']['page_id'] = 0;
				$return['block']['parent_id'] = $params['parent_id'];

			} else {
					
				$return['block'] = $this->cms_page_panel_model->new_block();
				$return['block']['panel_params'] = array();
				$return['block']['page_id'] = $params['page_id'];

			}

		}

		// no page page_id -> 0
		if ($return['block']['page_id'] == 999999) $return['block']['page_id'] = 0;

		if (!empty($return['block']['page_id'])){
			$return['cms_page'] = $this->cms_page_model->get_page($return['block']['page_id']);
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
			$filename = $GLOBALS['config']['base_path'].'modules/'.$module.'/config.json';
			if (file_exists($filename)){
				$json_data = file_get_contents($filename);
				$structure = json_decode($json_data, true);
				if (!empty($structure['panels'])){
					foreach($structure['panels'] as $panel){

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
		}
		
		// check if panel is list item on the same named page
		$panel_definition = $this->cms_panel_model->get_cms_panel_config($return['block']['panel_definition']);
		
		if (!empty($panel_definition['list']) && !empty($return['cms_page_id']) && $return['block']['panel_name'] == $panel_definition['module'].'/'.$return['cms_page']['slug']){
			if (empty($panel_definition['settings'])){
				$panel_structure = [];
			} else {
				$panel_structure = $panel_definition['settings'];
			}
		} else {
			$panel_structure = $panel_definition['item'];
		}
		
		$return['fk_data'] = $this->cms_panel_model->get_cms_panel_fk_data($panel_structure);

		$return['panel_params_structure'] = $panel_structure; // $this->cms_panel_model->get_cms_panel_definition($return['block']['panel_definition']);

		// print_r($return);

		if ((empty($return['independent_block']) || !empty($return['block']['parent_id'])) && $return['block']['panel_name'] === ''){

			$return['shortcuts'] = array();
			// get all possible panels on pages
			$pages = $this->cms_page_model->get_cms_pages();

			foreach($pages as $page){
				$blocks = $this->cms_page_panel_model->get_cms_page_panels_by(array('page_id' => $page['page_id'], ));
				foreach ($blocks as $block){
					if ($block['title'] !== ''){
						$return['shortcuts'][$block['block_id']] = $page['title'].' > '.$block['title'];
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