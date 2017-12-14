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
			$params['master_version'] = !empty($version_data['version']) ? $version_data['version'] : '';

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
			
			// check and update version information
			$master_data = $this->cms_update_model->get_master_version();
			$this->cms_update_model->rebuild();
			$local_data = $this->cms_update_model->get_version();
			
			$master_hash = !empty($master_data['version_hash']) ? $master_data['version_hash'] : 'error';
			if ($local_data['current_hash'] == $master_hash){
				// update local json
				$this->cms_update_model->update_version_cache([
						'version' => !empty($master_data['version']) ? $master_data['version'] : '',
						'version_hash' => !empty($master_data['version_hash']) ? $master_data['version_hash'] : '',
						'version_time' => !empty($master_data['version_time']) ? $master_data['version_time'] : '',
						'update_time' => time(),
				]);
			}

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
		$params['local_hash'] = $version_data['version_hash'];
		$params['current_hash'] = $version_data['current_hash'];
		$params['version_time'] = !empty($version_data['version_time']) ? $version_data['version_time'] : 0;
		$params['update_time'] = !empty($version_data['update_time']) ? $version_data['update_time'] : 0;
		
		// get master version
		if (empty($GLOBALS['config']['update']['is_master'])){
			$version_data = $this->cms_update_model->get_master_version();
			$params['master_version'] = !empty($version_data['version']) ? $version_data['version'] : '';
			$params['master_hash'] = !empty($version_data['version_hash']) ? $version_data['version_hash'] : '';
			$params['master_time'] = !empty($version_data['version_time']) ? $version_data['version_time'] : 0;
			if ($params['master_version'] != $params['local_version']){
				$params['can_update'] = true;
			}
		} else {
			// check if to increment master version
			if ($params['current_hash'] !== $params['local_hash']){
				$this->cms_update_model->increment_master_version();
				$local_data = $this->cms_update_model->get_version();
				$params['local_version'] = $local_data['version'];
				$params['local_hash'] = $local_data['version_hash'];
				$params['current_hash'] = $local_data['current_hash'];
			}				 	
			$params['master_version'] = 'This is master';
		}
		
		// if master and local hash are the same, then has to be the same version
		if (empty($params['local_version']) && $params['local_hash'] == $params['master_hash']){
			$params['local_version'] = $params['master_version'];
		}
		
		if ($params['local_version'] == $params['master_version'] && $params['current_hash'] == $params['master_hash']){
			$params['current_version'] = 'up to date';
		} else {
			$params['current_version'] = 'needs update';
			$params['can_update'] = true;
		}
		
		if (empty($GLOBALS['config']['update']['is_master']) && $params['master_hash'] != $params['current_hash']){
			$params['local_changes'] = true;
			$params['can_update'] = true;
		}

		return $params;

	}

}
