<?php defined('BASEPATH') OR exit('No direct script access allowed');

class cms_schema extends Controller {
	
	function __construct(){
	
		parent::__construct();
	
		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
	
	}
	
	function panel_action($params) {
		if (empty($params['do']) || $params['do'] !== 'fix_schema') {
			return [];
		}
		
		$key = trim($params['key'] ?? '');
		if (!$key) {
			return [
				'success' => false,
				'message' => 'No key provided'
			];
		}
		
		$this->load->model('cms/cms_schema_model');
		
		$success = $this->cms_schema_model->fix_schema($key);
		
		if ($success) {
			return ['success' => true];
		}
		
		return [
			'success' => false,
			'message' => 'Fix failed or no changes were needed'
		];
	}
	
	function panel_params($params) {
		
	    $this->load->model('cms/cms_schema_model');
	
	    $data = $this->cms_schema_model->get_schema_errors_with_status();
	
	    $params['grouped_errors'] = $data['grouped'];
	    $params['has_errors']     = $data['has_errors'];

	    return $params;
	
	}

}