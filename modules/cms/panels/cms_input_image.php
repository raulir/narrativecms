<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_image extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
		
		add_css('modules/cms/css/cms_input.scss');
		add_css('modules/cms/css/cms_images.scss');
		add_css('modules/cms/css/cms_images_page.scss');
		add_css('modules/cms/css/cms_video_view.scss');
		$GLOBALS['_panel_js'][] = 'modules/cms/js/dash/dash.min.js';
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_images.js';
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_media_view.js';
		$GLOBALS['_panel_js'][] = 'modules/cms/js/cms_video.js';
		
	}

	function panel_params($params){

		$this->load->helper('markdown_helper');

		if (!empty($params['value'])){
			$params['value'] = markdown_image_field_filename($params['value']);
		}

		if (empty($params['name_clean'])){
			$params['name_clean'] = $params['name'];
		}
		
		if (empty($params['category'])){
			$params['category'] = '';
		}
		
		// default image from module
		if (substr_count($params['value'], '/') == 1){
			
			list($module, $image) = explode('/', $params['value']);

			$filepath = $GLOBALS['config']['upload_path'].$module.'/'.$image;

			// if not in uploads, copy over
			if (!file_exists($filepath)){
			
				$original_path = $GLOBALS['config']['base_path'].'modules/'.$module.'/img/'.$image;
			
				if (!file_exists($GLOBALS['config']['upload_path'].$module.'/')){
					mkdir($GLOBALS['config']['upload_path'].$module.'/');
				}
			
				if (file_exists($original_path)){
					copy($original_path, $filepath);
				}
			
				// add to db
				$this->load->model('cms/cms_image_model');
			
				if (file_exists($original_path)){
					$this->cms_image_model->create_cms_image($module.'/', $image, $params['category']);
				}
			
			}

		}

		if (!file_exists($GLOBALS['config']['upload_path'].$params['value'])){

			$missing_error = true;

			if (strtolower(pathinfo($params['value'], PATHINFO_EXTENSION)) === 'mp4'){

				$this->load->model('cms/cms_image_model');
				$view_meta = $this->cms_image_model->get_video_view_meta($params['value']);

				if (!empty($view_meta['source_filename'])){

					$video_source = $view_meta['source_filename'];
					$has_source = file_exists($GLOBALS['config']['upload_path'].$video_source) && !is_dir($GLOBALS['config']['upload_path'].$video_source);
					$has_fallback = file_exists($GLOBALS['config']['upload_path'].$video_source.'.data/fallback.mp4');

					if ($has_source || $has_fallback){
						$missing_error = false;
					}

				}

			}

			if ($missing_error){
				$params['error'] = 'Missing image file<br>Update resources or database<br>or select a different image';
			}

		}
		
		if (!empty($params['params']['meta']) && $params['params']['meta'] == 'image'){
			$params['help'] = (!empty($params['help']) ? $params['help'].'||' : '').'{Page SEO image}';
		}

		return $params;

	}

}
