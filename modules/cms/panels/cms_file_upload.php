<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_file_upload extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action(){

		$return = array();

		$do = $this->input->post('do');
		if ($do == 'cms_file_upload'){
			 
			// collect data
			$this->load->library('upload', array('allowed_types' => '*', 'upload_path' => $GLOBALS['config']['upload_path'], ));
			 
			if (!empty($_FILES['new_file'])){
				$new_file = $this->upload->upload_image('new_file');

				if (!empty($new_file)){
						
					$this->load->model('cms_file_model');
					$return = $this->cms_file_model->create_cms_file('', $new_file);
						
					rename($GLOBALS['config']['upload_path'].$new_file, $GLOBALS['config']['upload_path'].$return['filename']);

				}
			}
				
		}

		return $return;

	}

}
