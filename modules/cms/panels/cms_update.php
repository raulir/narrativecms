<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_update extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$do = $this->input->post('do');

		if ($do == 'cms_update' && !empty($GLOBALS['config']['update']['allow_updates'])){

			$this->load->model('cms_update_model');

			$params['result'] = $this->cms_update_model->update();
			$params['ajax'] = true;

			$version_data = $this->cms_update_model->get_version();
			$params['local_version'] = $version_data['version'];

			$version_data = $this->cms_update_model->get_master_version();
			$params['master_version'] = $version_data['version'];

		} else if ($do == 'cms_update_list'){
			 
			$this->load->model('cms_update_model');
			$params['result'] = $this->cms_update_model->get_needed_files();
			 
		} else if ($do == 'cms_update_file'){ // updates file
			 
			$filename = $this->input->post('filename');
			$this->load->model('cms_update_model');

			$this->cms_update_model->update_file($filename);
			 
			$params['result']['filename'] = $filename;
			$params['result']['fn_hash'] = md5($filename);
			$params['result']['letter'] = $this->input->post('letter');
			 
		} else if ($do == 'cms_update_copy'){

			$this->load->model('cms_update_model');
			$this->cms_update_model->update_copy();

		}

		return $params;

	}

	function panel_params($params){

		if (!empty($params['ajax'])){
			return $params;
		}

		$this->load->model('cms_update_model');

		$params['can_update'] = false;

		// update local hashes
		$this->cms_update_model->rebuild();

		// get local version
		$version_data = $this->cms_update_model->get_version();
		$params['local_version'] = $version_data['version'];
		$params['local_hash'] = $version_data['hash'];

		// get master version
		if (empty($GLOBALS['config']['update']['is_master'])){
			$version_data = $this->cms_update_model->get_master_version();
			$params['master_version'] = $version_data['version'];
			$params['master_hash'] = $version_data['hash'];
			if ($params['master_version'] != $params['local_version']){
				$params['can_update'] = true;
			} else if ($params['master_hash'] != $params['local_hash']){
				$params['can_update'] = true;
				$params['local_changes'] = true;
			}
		} else {
			$params['master_version'] = 'This is master';
		}

		return $params;

	}

}
