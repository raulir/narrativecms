<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_grid extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		
	}
	
	function panel_action($params){
		
		if(!empty($params['do'])){
			
			if ($params['do'] == 'create_row'){
				
				$this->load->model('cms/cms_page_panel_model');
				
				$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
				$params['data'] = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], [
						'do' => 'C',
						'id' => $params['base_id'],
				]);
				
				print(json_encode(['result' => $params['data']], JSON_PRETTY_PRINT));
				
				die();
				
			} else if ($params['do'] == 'delete_row'){
				
				$this->load->model('cms/cms_page_panel_model');

				if(!empty($params['base_id'])){
					$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
					$base_name = $base['panel_name'];
				} else {
					$base_name = $params['base_name'];
				}
				
				$params['data'] = $this->run_panel_method($base_name, 'ds_'.$params['ds'], [
						'do' => 'D',
						'id' => $params['id'],
				]);
				
				print(json_encode(['result' => $params['data']], JSON_PRETTY_PRINT));
				
				die();
				
			}
				
		}
		
		return $params;
		
	}

	function panel_params($params){
		
		$params['data'] = [];

		if(!empty($params['base_id']) || !empty($params['base_name'])){
		
			$this->load->model('cms/cms_page_panel_model');
			$this->load->model('cms/cms_panel_model');
				
			if (!empty($params['base_id'])){
				$base = $this->cms_page_panel_model->get_cms_page_panel($params['base_id']);
			} else {
				$panel_config = $this->cms_panel_model->get_cms_panel_config($params['base_name']);
				$base['panel_name'] = $params['base_name'];
				if (!empty($panel_config['extends'])){
					$base['_extends'] = $panel_config['extends'];
				}
			}
			
			$params['fields'] = $params['fields'] ?? [];

			if (!empty($params['operations']) && stristr($params['operations'], 'S')){

				$ds_params = [
					'do' => 'S',
					'id' => $params['base_id'] ?? 0,
					'fields' => $params['fields'],
				];

				if (!empty($base['_extends'])){
					$ds_params['_extends'] = $base['_extends'];
				}
				
				$fields = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], $ds_params);

				if (!empty($fields['fields'])){
					$params['fields'] = $fields['fields'];
				} else {
					$params['fields'] = $fields;
				}
				
				if (!empty($fields['_no_cache'])) unset($params['fields']['_no_cache']);

			}

			usort($params['fields'], function($a, $b){
				if (empty($a['order'])) $a['order'] = 20;
				if (empty($b['order'])) $b['order'] = 20;
				return ((int)$a['order'] > $b['order'])*2 - 1;
			});
			
			$ds_params = [
					'do' => 'L',
					'id' => $params['base_id'] ?? 0,
			];
			
			if (!empty($base['_extends'])){
				$ds_params['_extends'] = $base['_extends'];
			}
			
			$params['data'] = $this->run_panel_method($base['panel_name'], 'ds_'.$params['ds'], $ds_params);
			
			if (!empty($params['data']['_no_cache'])) unset($params['data']['_no_cache']);
		
		}
		
		$params['_params'] = &$params;

		return $params;

	}

}
