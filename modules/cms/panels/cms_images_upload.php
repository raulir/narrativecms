<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_images_upload extends CI_Controller {

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}
		
		$this->load->model('cms/cms_image_model');
		
	}
	
	// common functionality of file uploading
	function image_upload($input_name, $category){

		$this->load->library('upload', array('allowed_types' => 'svg|gif|jpg|png|jpeg|mp4', 'upload_path' => $GLOBALS['config']['upload_path'], ));
		
		$new_image = $this->upload->upload_image($input_name);
		
		$filename = '';
		
		if (!empty($new_image)){

			// move it to year/month directory
			if (!file_exists($GLOBALS['config']['upload_path'].date('Y'))){
				mkdir($GLOBALS['config']['upload_path'].date('Y'));
			}

			if (!file_exists($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'))){
				mkdir($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'));
			}

			$filename = $this->cms_image_model->create_cms_image(date('Y').'/'.date('m').'/', $new_image, $category);

			// delete existing images
			if (file_exists($GLOBALS['config']['upload_path'].$filename)){
				$this->cms_image_model->delete_cms_image_by_filename($filename, false);
			}
			if (is_dir($GLOBALS['config']['upload_path'].$filename.'.data')){
				array_map('unlink', glob($GLOBALS['config']['upload_path'].$filename.'.data'.'/*.*'));
				rmdir($GLOBALS['config']['upload_path'].$filename.'.data');
			}

			rename($GLOBALS['config']['upload_path'].$new_image, $GLOBALS['config']['upload_path'].$filename);
			if (is_dir($GLOBALS['config']['upload_path'].$filename.'.data')){			
				rename($GLOBALS['config']['upload_path'].$new_image.'.data', $GLOBALS['config']['upload_path'].$filename.'.data');
			}
			
		}

		return $filename;
		
	}

	function panel_action(){
					
		$do = $this->input->post('do');
		if ($do == 'cms_images_upload'){

			$return = ['filenames' => [], ];

			// normalise files array
			$input_name = 'new_image';
			$field_names = array('name', 'type', 'tmp_name', 'error', 'size', );
			$keys = array();
			foreach($field_names as $field_name){
				if (isset($_FILES[$input_name][$field_name])) foreach($_FILES[$input_name][$field_name] as $key => $value){
					$_FILES[$input_name.'_'.$key][$field_name] = $value;
					$keys[$key] = $key;
				}
			}
			unset($_FILES[$input_name]);

			$category = $this->input->post('category');

			foreach ($keys as $key){

				$return['filenames'][] = $this->image_upload($input_name.'_'.$key, $category);

			}
			
			return $return;

		} else if ($do == 'cms_images_replace'){
			
			$this->load->model('cms/cms_page_panel_model');
			$this->load->model('cms/cms_page_model');
				
			// collect data
			$filename = $this->input->post('filename');
			$category = $this->input->post('category');
			
			$new_image = $this->image_upload('replace_image', $category);
				
			if (!empty($new_image)){

				// replace all image occassions
				$this->cms_page_panel_model->swap_param_value($filename, $new_image);

				// add page images to count
				$pages = $this->cms_page_model->get_cms_pages();

				foreach($pages as $page){
						
					if ($filename == $page['image']){
						$this->cms_page_model->update_page($page['cms_page_id'], ['image' => $new_image]);
					}
						
				}

			}
			
			return ['filename' => $new_image];
			
		}

		return [];

	}

}
