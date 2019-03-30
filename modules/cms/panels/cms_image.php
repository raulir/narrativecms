<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_image extends CI_Controller {

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
		$this->load->model('cms_keyword_model');

		if (empty($params['filename'])) {
			$params['filename'] = '';
		}

		// get possible categories
		$params['categories'] = $this->cms_image_model->get_cms_image_categories();

		$image = $this->cms_image_model->get_cms_image_by_filename($params['filename']);

		$meta = json_decode($image['meta'], true);
		$params['author'] = !empty($meta['author']) ? $meta['author'] : '';
		$params['copyright'] = !empty($meta['copyright']) ? $meta['copyright'] : '';
		$params['description'] = !empty($meta['description']) ? $meta['description'] : '';

		$params['category'] = $image['category'];

		$params['possible_keywords'] = $this->cms_keyword_model->get_cms_keywords();

		if(!empty($image['keyword'])){
			$params['keywords'] = explode(',', $image['keyword']);
		}

		return $params;

	}

}
