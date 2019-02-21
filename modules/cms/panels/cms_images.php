<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_images extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		if (empty($params['filename'])) {
			$params['filename'] = '';
		}

		// get possible categories
		$this->load->model('cms_image_model');
		$params['categories'] = $this->cms_image_model->get_cms_image_categories();
		if (!empty($params['category']) && empty($params['categories'][$params['category']])){
			$params['categories'][$params['category']] = ucfirst($params['category']);
		}

		if (empty($params['category'])){
			$params['category'] = '';
		}

		return $params;

	}

}
