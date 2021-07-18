<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_textarea extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		$this->load->model('cms/cms_css_model');
		$this->cms_css_model->add_css('modules/cms/css/cms_input.scss');
		
	}

	function panel_params($params){

		$field_empty = !isset($data[$params['name']]);
		
		if (!empty($params['mandatory'])){
			$mandatory_class = ' cms_input_mandatory ';
			$mandatory_label = ' *';
		} else {
			$mandatory_class = '';
			$mandatory_label = '';
		}
		
		if (!empty($params['max_chars'])){
			$max_chars_class = ' admin_max_chars ';
			$max_chars_data = ' data-max_chars="'.$params['max_chars'].'" ';
		} else {
			$max_chars_class = '';
			$max_chars_data = '';
		}
		
		if (!empty($params['default']) && substr($params['default'],0,6) == ':meta:'){
			list($a, $b, $meta_src, $meta_field) = explode(':', $params['default']);
			$meta_class = ' cms_meta ';
			$meta_data = ' data-meta_src="'.$meta_src.'" data-meta_field="'.$meta_field.'" ';
			$params['default'] = '';
		} else {
			$meta_class = '';
			$meta_data = '';
		}
		
		$params['label'] = $params['label'].$mandatory_label;
		$params['lines'] = !empty($params['lines']) ? $params['lines'] : '3';
		$params['width'] = !empty($params['width']) ? $params['width'] : 'narrow';
		
		$params['extra_data'] = $max_chars_data.' '.$meta_data.' '
				.' data-html="'.(!empty($params['html']) ? $params['html'] : '').'" '
				.' data-html_class="'.(!empty($params['html_class']) ? $params['html_class'] : '').'" '
				.' data-html_css="'.(!empty($params['html_css']) ? $params['html_css'] : '').'" '
				.(!empty($params['styles']) ? ' data-styles="'.str_replace('"','~',json_encode($params['styles'])).'"' : '');

		$params['max_chars_class'] = $max_chars_class;
		$params['meta_class'] = $meta_class;
		$params['mandatory_class'] = $mandatory_class;

		if (!empty($params['html'])){
			
			$GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/tinymce/tinymce.min.js', 'no_pack' => 1, 'sync' => '', );
			
			if (stristr($params['html'], 'M')){
				$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_input_image.js';
			}

		}

		return $params;

	}

}
