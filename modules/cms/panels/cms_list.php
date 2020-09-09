<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_list extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/jquery/jquery-ui.min.js', );
		$GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/cms_cookie.js', );
		
	}

	function panel_params($params){

		$params['filter_fields_values'] = array();

		if (!empty($params['filter_fields'])){

			$this->load->model('cms/cms_panel_model');
			$this->load->model('cms/cms_page_panel_model');
				
			// load definition
			$panel_definition = $this->cms_panel_model->get_cms_panel_definition($params['filter']['panel_name']);
			
			// filter has to support both \ and non-\ panel names
			if (is_array($params['filter']['panel_name'])){
				$params['filter']['panel_name'] = implode('|', $params['filter']['panel_name']);
			}

			// for show
			$panel_definition[] = array(
					'type' => 'select',
					'name' => 'show',
					'label' => 'Show',
					'values' => array('No', 'Yes', ),
			);

			foreach($params['filter_fields'] as $filter_field => $filter_field_label){
				// get values
				foreach($panel_definition as $panel_field){
						
					// if select
					if (!empty($panel_field['name']) && $panel_field['type'] == 'select' && $panel_field['name'] == $filter_field){
						$params['filter_fields_values'][$filter_field] = $panel_field['values'];
					}
						
					// if fk
					if (!empty($panel_field['name']) && $panel_field['type'] == 'fk' && $panel_field['name'] == $filter_field && 
							!empty($panel_field['target']) && $panel_field['target'] == 'block'){

						$panel_name = str_replace('_id', '', $panel_field['name']);

						$target_a = $this->cms_page_panel_model->get_list($panel_name, ['show' => [0,1]]);

						if (count($target_a)){
								
							$params['filter_fields_values'][$filter_field] = array();
							foreach($target_a as $row){

								$params['filter_fields_values'][$filter_field][$row['cms_page_panel_id']] = !empty($row['heading']) ? $row['heading'] : $row['cms_page_panel_id'];
									
							}
								
						}

					} else if (!empty($panel_field['name']) && $panel_field['type'] == 'fk' && $panel_field['name'] == $filter_field){
						
						$target_a = $this->cms_page_panel_model->get_list($panel_field['list'], ['show' => [0,1]]);
						
						if (count($target_a)){
						
							$params['filter_fields_values'][$filter_field] = [];
							foreach($target_a as $key => $row){
								
								$params['filter_fields_values'][$filter_field][$key] = $this->run_panel_method($panel_field['list'], 'panel_heading', $row);
									
							}
						
						}
						
					}
						
				}
			}

		}
		
		if (is_array($params['filter']['panel_name'])){
			$params['filter']['panel_name'] = $params['filter']['panel_name'][0];
		}
		
		$params['new_panel_name'] = $params['filter']['panel_name'];
		if (stristr($params['filter']['panel_name'], '|')){
			
			$panel_names = explode('|', $params['filter']['panel_name']);
			
			$params['new_panel_name'] = $panel_names[0];
			
			// if array, get first with /
			foreach($panel_names as $_cms_panel){
				if (stristr($_cms_panel, '/')){
					$params['new_panel_name'] = $_cms_panel;
					break;
				}
			}
				
		}

		return $params;

	}

}
