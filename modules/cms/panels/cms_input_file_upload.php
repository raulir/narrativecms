<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_file_upload extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action($params){

		$return = array();

		$do = $params['cms_do'] ?? false;
		if ($do == 'cms_input_file_upload'){
			 
			// Any type: CMS files are kept in the data folder, which is never served (cms/file API only)
			$this->load->library('upload', array('allowed_types' => '*', 'upload_path' => $GLOBALS['config']['data_path'], ));

			if (!empty($_FILES['new_file'])){
				$new_file = $this->upload->upload_image('new_file');

				if (!empty($new_file)){

					$this->load->model('cms/cms_file_model');
					$return = $this->cms_file_model->create_cms_file($new_file);

					rename($GLOBALS['config']['data_path'].$new_file, $this->cms_file_model->file_path($return['filename']));

				}
			}
				
		}

		return $return;

	}

}
