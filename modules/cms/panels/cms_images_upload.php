<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_images_upload extends MY_Controller{

	function __construct(){

		parent::__construct();

		// check if user
		if(empty($_SESSION['cms_user']['cms_user_id'])){
			header('Location: '.$GLOBALS['config']['base_url'].'cms_login/', true, 302);
			exit();
		}

	}

	function panel_action(){

		$this->load->model('cms_image_model');
					
		$do = $this->input->post('do');
		if ($do == 'cms_images_upload'){

			$return = ['filenames' => [], ];

			// collect data
			$this->load->library('upload', array('allowed_types' => 'svg|gif|jpg|png|jpeg', 'upload_path' => $GLOBALS['config']['upload_path'], ));

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

			foreach ($keys as $key){

				$new_image = $this->upload->upload_image($input_name.'_'.$key);
				if (!empty($new_image)){

					// move it to year/month directory
					if (!file_exists($GLOBALS['config']['upload_path'].date('Y'))){
						mkdir($GLOBALS['config']['upload_path'].date('Y'));
					}

					if (!file_exists($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'))){
						mkdir($GLOBALS['config']['upload_path'].date('Y').'/'.date('m'));
					}

					$category = $this->input->post('category');
					$return['filename'] = $this->cms_image_model->create_cms_image(date('Y').'/'.date('m').'/', $new_image, $category);

					$return['filenames'][] = $return['filename'];
					
					// delete existing images
					if(file_exists($GLOBALS['config']['upload_path'].$return['filename'])){
						$this->cms_image_model->delete_cms_image_by_filename($return['filename'], false);
					}

					rename($GLOBALS['config']['upload_path'].$new_image, $GLOBALS['config']['upload_path'].$return['filename']);

				}

			}
			
			return $return;

		} else if ($do == 'cms_images_replace'){
			
			// collect data
			$filename = $this->input->post('filename');
			
			$this->load->library('upload', array('allowed_types' => 'svg|gif|jpg|png|jpeg', 'upload_path' => $GLOBALS['config']['upload_path'], ));
			
			$new_image = $this->upload->upload_image('replace_image');

			if (!empty($new_image)){
			
				// remove old image files
				$this->cms_image_model->delete_cms_image_by_filename($filename, false);
				
				// copy new image to place
				rename($GLOBALS['config']['upload_path'].$new_image, $GLOBALS['config']['upload_path'].$filename);
				
				// update image hash
				$this->cms_image_model->refresh_cms_image_hash($filename);
			
			}
			
			return ['filename' => $GLOBALS['config']['upload_url'].$filename.'?v='.time(), ];
			
		}

		return [];

	}

}
