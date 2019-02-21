<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_keyword extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action(){

		$this->load->model('cms_keyword_model');
		$this->load->model('cms_image_model');
		 
		$do = $this->input->post('do');
		if ($do == 'cms_keyword_save'){

			// collect data
			$cms_keyword_id = $this->input->post('cms_keyword_id');
			$data['keyword'] = trim($this->input->post('keyword'));
			 
			if ($cms_keyword_id == '' && !empty($data['keyword'])){

				$result = $this->cms_keyword_model->create_cms_keyword($data['keyword'], $data);
				if ($result){
					header('Location: '.$GLOBALS['config']['base_url'].'admin/keyword/'.$data['keyword'].'/', true, 302);
					exit();
				}

			} else if (!empty($data['keyword']) && $cms_keyword_id !== $data['keyword']){

				$result = $this->cms_keyword_model->update_cms_keyword($cms_keyword_id, $data);
				if ($result){
					header('Location: '.$GLOBALS['config']['base_url'].'admin/keyword/'.$data['keyword'].'/', true, 302);
					exit();
				}

			}

			return array('cms_keyword_id' => $cms_keyword_id, );

		}

		if ($do == 'cms_keyword_delete'){
			 
			$cms_keyword_id = $this->input->post('cms_keyword_id');
			 
			// delete keyword
			$this->cms_keyword_model->delete_cms_keyword($cms_keyword_id);
			 
			// clean up ...
			$this->cms_image_model->purge_keyword($cms_keyword_id);
			 
		}

	}

	function panel_params($params){

		$params['cms_keyword_id'] = urldecode($params['cms_keyword_id']);

		return $params;

	}

}
