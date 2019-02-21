<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_user_operations extends CI_Controller {

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

		if ($do == 'cms_user_save'){
			 
			$cms_user_id = $this->input->post('cms_user_id');
			$rights = $this->input->post('rights');
			$username = $this->input->post('username');
			$password = $this->input->post('password');
			$name = $this->input->post('name');
			$email = $this->input->post('email');
			$sort = $this->input->post('sort');

			$this->load->model('cms_user_model');
			 
			if (!is_array($rights)){
				$rights = [];
			}

			// save data
			if (empty($cms_user_id)){

				$this->cms_user_model->create_cms_user([
						'sort' => $sort,
						'access' => implode(',', $rights),
						'username' => $username,
						'name' => $name,
						'email' => $email,
						'password' => md5($username.$password),
				]);

			} else {

				$data = [
						'sort' => $sort,
						'access' => implode(',', $rights),
						'username' => $username,
						'name' => $name,
						'email' => $email,
				];

				if(!empty($password)){
					$data['password'] = md5($username.$password);
				}

				$this->cms_user_model->update_cms_user($cms_user_id, $data);
				 
			}
			 
		} elseif ($do == 'cms_user_delete'){
			 
			$cms_user_id = $this->input->post('cms_user_id');

			$this->load->model('cms_user_model');
			 
			$this->cms_user_model->delete_cms_user($cms_user_id);
			 
		}

		return $params;

	}

}
