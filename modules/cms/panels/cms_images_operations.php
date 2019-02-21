<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_images_operations extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$this->load->model('cms_image_model');

		$do = $this->input->post('do');
		if ($do == 'cms_images_delete_by_filename'){
			 
			// collect data
			$filename = $this->input->post('filename');
			 
			$this->cms_image_model->delete_cms_image_by_filename($filename);
			 
			$return = array();
			 
		}
		if ($do == 'cms_images_check_by_filename'){
			 
			$filename = $this->input->post('filename');
			 
			$image = $this->cms_image_model->get_cms_image_by_filename($filename);
			 
			$return['filename'] = !empty($image['filename']) ? $image['filename'] : '';
			 
			if (!empty($image['meta'])){
				$return = array_merge($return, json_decode($image['meta'], true));
			}

		}
		if ($do == 'cms_images_save'){
			 
			$filename = $this->input->post('filename');
			$category = $this->input->post('category');
			$keywords = $this->input->post('keywords');
			$meta = array(
					'author' => $this->input->post('author'),
					'copyright' => $this->input->post('copyright'),
					'description' => $this->input->post('description'),
			);
			 
			$this->cms_image_model->update_cms_image($filename, array(
					'category' => empty($category) ? '' : $category,
					'meta' => json_encode($meta),
					'keyword' => $keywords,
			));
			 
			$return = array();
			 
		}

		return $return;

	}

}
 