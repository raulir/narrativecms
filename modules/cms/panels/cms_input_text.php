<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_text extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		
	}
	
	function panel_params($params){

		if (!empty($params['default']) && substr($params['default'],0,6) == ':meta:'){
			list($a, $b, $meta_src, $meta_field) = explode(':', $params['default']);
			$params['meta_class'] = ' cms_meta ';
			$meta_data = ' data-meta_src="'.$meta_src.'" data-meta_field="'.$meta_field.'" ';
			$params['default'] = '';
		} else {
			$params['meta_class'] = '';
			$meta_data = '';
		}

		if (!empty($params['max_chars'])){
			$params['max_chars_class'] = ' admin_max_chars ';
			$max_chars_data = ' data-max_chars="'.$params['max_chars'].'" ';
		} else {
			$params['max_chars_class'] = '';
			$max_chars_data = '';
		}
		
		$params['extra_data'] = $max_chars_data.' '.$meta_data.' ';
		
		$params['default_class'] = '';
		if (isset($params['default'])){
			$params['default_class'] = ' cms_input_default ';
		}
		
		$params['mandatory_class'] ??= '';
		$params['extra_class'] ??= '';
		
		return $params;
		
	}

}
