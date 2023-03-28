<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class form_upload extends CI_Controller {

	// common functionality of file uploading
	function file_upload($input_name){
		
		$this->load->model('cms/cms_file_model');
		
		$this->load->library('upload', ['upload_path' => $GLOBALS['config']['upload_path'], ]);
		
		$new_file = $this->upload->upload_image($input_name);
		
		$return = $this->cms_file_model->create_cms_file('cv/', $new_file, true);
// _print_r($return);		
		rename($GLOBALS['config']['upload_path'].$return['name_original'], $GLOBALS['config']['upload_path'].$return['filename']);
				
		if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'){
			$url = 'https://';
		} else {
			$url = 'http://';
		}
		$url.= $_SERVER['HTTP_HOST'];
		
		
		return [
				'filename' => $url.$GLOBALS['config']['upload_url'].$return['filename'],
				'filename_clean' => $new_file,
		];
		
	}

	function panel_action($params){
		
//		_print_r($params);
//		_print_r($_FILES);
		
		$do = $this->input->post('do');
		if ($do == 'form_upload'){

			$input_name = 'file';
			
			
			/*
			
			$field_names = ['name', 'type', 'tmp_name', 'error', 'size', ];
			$keys = [];
_print_r($field_names);
			foreach($field_names as $field_name){
				if (isset($_FILES[$input_name][$field_name])) foreach($_FILES[$input_name][$field_name] as $key => $value){
					$_FILES[$input_name.'_'.$key][$field_name] = $value;
					$keys[$key] = $key;
				}
			}
			unset($_FILES[$input_name]);

			foreach ($keys as $key){
			*/
				$return = $this->file_upload($input_name);
/*
			}
	*/		
			return $return;

		}

		return [];

	}

}
