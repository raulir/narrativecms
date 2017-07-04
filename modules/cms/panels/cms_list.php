<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_list extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->js[] = array('script' => 'js/jquery-ui.min.js', );

	}

	function panel_params($params){

		$params['filter_fields_values'] = array();

		if (!empty($params['filter_fields'])){

			$this->load->model('cms_panel_model');
			$this->load->model('cms_page_panel_model');
				
			// load definition
			$panel_definition = $this->cms_panel_model->get_cms_panel_definition($params['filter']['panel_name']);

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
					if (!empty($panel_field['name']) && $panel_field['type'] == 'fk' && 	$panel_field['name'] == $filter_field && $panel_field['target'] == 'block'){

						$panel_name = str_replace('_id', '', $panel_field['name']);

						$target_a = $this->cms_page_panel_model->get_list($panel_name, ['show' => [0,1]]);

						if (count($target_a)){
								
							$params['filter_fields_values'][$filter_field] = array();
							foreach($target_a as $row){

								$params['filter_fields_values'][$filter_field][$row['block_id']] = !empty($row['heading']) ? $row['heading'] : $row['block_id'];
									
							}
								
						}

					}
						
				}
			}

		}

		return $params;

	}

}
