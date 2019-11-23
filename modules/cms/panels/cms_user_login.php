<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_user_login extends CI_Controller {

	function panel_action($params){

		$do = $this->input->post('do');
		if ($do == 'cms_user_login'){

			$username = $this->input->post('username');
			$password = $this->input->post('password');

			$this->load->model('cms/cms_user_model');

			$cms_user_data = $this->cms_user_model->get_cms_user_login_data($username, $password);

			if (!empty($cms_user_data)){

				$_SESSION['cms_user'] = $cms_user_data;

				header('Location: '.$GLOBALS['config']['base_url'].'admin/', true, 302);
				exit();
					
			}

		}

		return $params;

	}

	function panel_params($params){

		return [];

	}

}
