<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class generate extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}
	
	function panel_action($params){

		$this->load->model('download/ai_model');
		
		$do = $this->input->post('do');

		if ($do == 'generate'){
			$this->ai_model->generate_texts($params['download_id']);
		}
		
		return $params;

	}
	
	function panel_params($params){

		return $params;
		
	}

}
