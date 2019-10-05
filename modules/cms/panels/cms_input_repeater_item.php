<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_repeater_item extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->load->helper('cms/cms_fields_helper');
		
	}

	function panel_params($params){
		
		/*
		
		if (!empty($params['json_fields']))
	
		// find correct panel to display
		if (!stristr($params['type'], '/')){
				
			// cms panel
			$filename = $GLOBALS['config']['base_path'].'modules/cms/templates/cms_input_'.str_replace('cms_', '', $params['type']).'.tpl.php';
			if (file_exists($filename)){
				$params['panel_name'] = 'cms/cms_input_'.str_replace('cms_', '', $params['type']);
			}
				
		} else {
				
			// module panel
			$params['panel_name'] = $params['type'];
				
		}
	
		$params['label'] = !empty($params['label']) ? $params['label'] : '[no label]';
		$params['help'] = !empty($params['help']) ? $params['help'] : '';
	
		// copy params to params for easier manipulation
		$params['params'] = $params;
	
		*/
		
//		print_r($params);
	
		return $params;
	
	}

}
