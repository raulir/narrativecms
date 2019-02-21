<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_user extends CI_Controller {

	function panel_action($params){

		$do = $this->input->post('do');
		if ($do == 'admin_login'){

			$username = $this->input->post('username');
			$password = $this->input->post('password');

			$this->load->model('cms_user_model');

			$cms_user_data = $this->cms_user_model->get_cms_user_login_data($username, $password);

			if (!empty($cms_user_data)){

				$_SESSION['cms_user'] = $cms_user_data;

				header('Location: '.$GLOBALS['config']['base_url'].'admin/', true, 302);
				exit();
					
			}

		}

		if ($do == 'admin_logout'){
			unset($_SESSION['cms_user']);
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		if ($do == 'admin_logout_soft'){
			unset($_SESSION['cms_user']);
		}

		return $params;

	}

	function panel_params($params){

		return array();

	}

}
