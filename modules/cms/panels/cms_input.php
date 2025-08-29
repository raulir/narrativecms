<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	
	}
	
	function panel_params($params){
		
		$params['mandatory_class'] = $params['mandatory'] ?? '' ? ' cms_input_mandatory ' : '';
		$params['label'] .= $params['mandatory'] ?? '' ? ' *' : '';
		
		// find correct panel to display
		if (!stristr($params['type'], '/')){
			
			if($params['type'] == 'color'){
				$params['type'] = 'colour';
			}
			
			// cms panel
			$filename = $GLOBALS['config']['base_path'].'modules/cms/templates/cms_input_'.str_replace('cms_', '', $params['type']).'.tpl.php';
			if (file_exists($filename)){
				$params['panel_name'] = 'cms/cms_input_'.str_replace('cms_', '', $params['type']);
			}
			
		} else {
			
			// module panel
			$params['panel_name'] = $params['type'];
			list($params['module'], $rest) = explode('/', $params['panel_name']);
			
		}
		
		$params['label'] = !empty($params['label']) ? $params['label'] : '[no label]';
		$params['help'] = !empty($params['help']) ? $params['help'] : '';

		if (!empty($params['groups']) && !is_array($params['groups'])){
				$params['groups'] = [$params['groups']];
		}
		
		// copy params to params for easier manipulation
		$params['params'] = $params;
				
		return $params;
		
	}

}
