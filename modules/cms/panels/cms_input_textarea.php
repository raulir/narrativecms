<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_textarea extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_input_textarea.scss');

		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_translate_string.js';
		
	}

	function panel_params($params){

		$field_empty = !isset($data[$params['name']]);

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
		
		$params['lines'] = !empty($params['lines']) ? $params['lines'] : '3';
		$params['width'] = !empty($params['width']) ? $params['width'] : 'narrow';
		
		$params['extra_data'] = $max_chars_data.' '.$meta_data.' '
				.' data-html="'.(!empty($params['html']) ? $params['html'] : '').'" '
				.' data-html_class="'.(!empty($params['html_class']) ? $params['html_class'] : '').'" '
				.' data-html_css="'.(!empty($params['html_css']) ? $params['html_css'] : '').'" '
				.(!empty($params['styles']) ? ' data-styles="'.str_replace('"','~',json_encode($params['styles'])).'"' : '');

		$params['max_chars_class'] = $max_chars_class;
		$params['meta_class'] = $meta_class;

		if (!empty($params['md']) && empty($params['readonly'])){

			if (empty($params['extra_data'])){
				$params['extra_data'] = '';
			}

			$params['extra_data'] .= ' data-md="1"';

			if (!empty($params['md_filter'])){
				$params['extra_data'] .= ' data-md_filter="'.$params['md_filter'].'"';
			}

		} else if (!empty($params['html']) && empty($params['readonly'])){
			
			$GLOBALS['_panel_js'][] = array('script' => 'modules/cms/js/tinymce/tinymce.min.js', 'no_pack' => 1, 'sync' => '', );
			
			if (stristr($params['html'], 'M')){
				$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_input_image.js';
				add_css('modules/cms/css/cms_images.scss');
				add_css('modules/cms/css/cms_images_page.scss');
				add_css('modules/cms/css/cms_video_view.scss');
				$GLOBALS['_panel_js'][] = ['script' => 'modules/cms/js/dash/dash.min.js', 'no_pack' => 1, 'sync' => 'defer', ];
				$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_images.js';
				$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_media_view.js';
				$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_video.js';
			}

		}

		return $params;

	}

	function panel_action($params){

		if (empty($params['md_preview'])){
			return $params;
		}

		$text = (string)($params['text'] ?? '');
		$cms_page_panel_id = (int)($params['cms_page_panel_id'] ?? 0);
		$md_filter = trim((string)($params['md_filter'] ?? ''));

		$this->load->helper('markdown_helper');

		$filter_params = [
			'text' => $text,
			'cms_page_panel_id' => $cms_page_panel_id,
		];

		if ($md_filter !== ''){
			$filter_params = markdown_apply_md_filter($md_filter, $filter_params);
		}

		return [
			'html' => markdown_render_body($filter_params['text'] ?? $text, $filter_params['images'] ?? []),
		];

	}

}
