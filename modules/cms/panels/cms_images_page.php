<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_images_page extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_params($params){

		$this->load->model('cms_image_model');

		$images = $this->cms_image_model->get_cms_images($params['page'], $params['limit'], $params['category'], $params['search']);

		$params['cms_images'] = $images['result'];

		$params['cms_images_max_page'] = max(floor(( $images['count'] - 1 ) / $params['limit']), 0);

		$params['cms_images_current'] = $params['page'];

		return $params;

	}

}
