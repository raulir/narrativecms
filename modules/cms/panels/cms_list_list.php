<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_list_list extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('cms/cms_panel_model');
		
		$config = $this->cms_panel_model->get_cms_panel_config($params['panel_name']);

		if ($params['id_field'] == 'block_id'){
			$params['id_field'] = 'cms_page_panel_id';
		}
		
		$return['edit_base'] = $params['edit_base'];
		$return['id_field'] = $params['id_field'];
		$return['no_sort'] = empty($params['no_sort']) ? 0 : 'no_sort';
		
		if (stristr($params['panel_name'], '|')){
			$params['panel_name'] = explode('|', $params['panel_name']);
		}
			
		$filter = ['panel_name' => $params['panel_name'], 'cms_page_id' => [999999,0], 'sort!' => '0', ];
			
		if (!empty($params['filters'])){
			$filter = array_merge($filter, $params['filters']);
		}
		
		// TODO: refactor array counting to count_cms_page_panels_by
		if (is_array($params['panel_name'])){
			
			$return['total'] = 0;
			foreach($params['panel_name'] as $panel_name){
				$return['total'] += $this->cms_page_panel_model->count_cms_page_panels_by(array_merge($filter, ['panel_name' => $panel_name, 'cms_page_id' => 999999, ]));
				$return['total'] += $this->cms_page_panel_model->count_cms_page_panels_by(array_merge($filter, ['panel_name' => $panel_name, 'cms_page_id' => 0, ]));
			}
			
		} else {
			
			$return['total'] = $this->cms_page_panel_model->count_cms_page_panels_by(array_merge($filter, ['panel_name' => $params['panel_name'], 'cms_page_id' => 999999, ]));
			$return['total'] += $this->cms_page_panel_model->count_cms_page_panels_by(array_merge($filter, ['panel_name' => $params['panel_name'], 'cms_page_id' => 0, ]));
		
		}
		
		$filter['_start'] = !empty($params['start']) ? $params['start'] : 0;
		$filter['_limit'] = !empty($params['limit']) ? $params['limit'] : 0;
		$return['list'] = $this->cms_page_panel_model->get_cms_page_panels_by($filter);

		// get page panel titles
		if(!empty($config['list']['title_panel'])){
			$return['title_panel'] = $config['list']['title_panel'];
		}
		
		foreach($return['list'] as $key => $block){
			
			if (empty($block['_panel_heading']) && empty($return['title_panel'])){
				$return['list'][$key]['_panel_heading'] = $this->run_panel_method($block['panel_name'], 'panel_heading', $block);
			}
			
		}

		return $return;

	}

}
