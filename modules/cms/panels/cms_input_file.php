<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_input_file extends \Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

		add_css('modules/cms/css/cms_input.scss');
	
	}
	
	function panel_action($params){
		
		if (!empty($params['cms_do']) && $params['cms_do'] == 'cms_file_rename'){

			$this->load->model('cms/cms_file_model');

			// Only a stored CMS file, in its own folder of the data folder; the extension stays
			$file = $this->cms_file_model->get_cms_file_by_filename((string)($params['old_name'] ?? ''));
			$old_path = !empty($file['filename']) ? $this->cms_file_model->file_path($file['filename']) : '';
			$new_name = trim((string)($params['new_name'] ?? ''));

			if ($old_path === '' || !is_file($old_path) || $new_name === ''){
				return ['error' => 'This file cannot be renamed'];
			}

			$extension = pathinfo($file['filename'], PATHINFO_EXTENSION);
			$new_short_name = $this->cms_file_model->sanitize_filename($new_name.'.'.$extension);
			$new_filename = dirname($file['filename']).'/'.$new_short_name;
			$new_path = $this->cms_file_model->file_path($new_filename);

			if ($new_path === '' || file_exists($new_path) || !rename($old_path, $new_path)){
				return ['error' => 'This file cannot be renamed'];
			}

			// The record and every block param that names the file
			$this->cms_file_model->rename_file($file['filename'], $new_filename, $new_short_name);

			return [
					'new_filename' => $new_filename,
					'new_short_name' => $new_short_name,
			];

		}

		return $params;
		
	}

	function panel_params($params){

		if (empty($params['name_clean'])) {
			$params['name_clean'] = $params['name'] ?? '';
		}

		return $params;

	}
	
}
