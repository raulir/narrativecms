<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_image extends CI_Controller {

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

		if(!file_exists($GLOBALS['config']['upload_path'].$params['value'])){
			$params['error'] = 'Missing image file<br>Update resources or database<br>or select a different image';
		}
		
		if (!empty($params['params']['meta']) && $params['params']['meta'] == 'image'){
			$params['help'] = (!empty($params['help']) ? $params['help'].'||' : '').'{Page SEO image}';
		}

		return $params;

	}

}
