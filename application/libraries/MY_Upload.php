<?php
class MY_Upload extends CI_Upload {

	function upload_image($image_name){
		
		$return = false;
		if ($_FILES[$image_name]['error'] !== 4){
			if ( ! $this->do_upload($image_name)) {
				// TODO: if upload didnt succeed do better handling
				print('Problem with file upload. Upload path = '.$this->upload_path);
				print_r($this->display_errors());
				print_r($_FILES);
				die();
			} else {
				$upload_data = $this->data();
				$return = $upload_data['file_name'];
			}
		}
		
		return $return;
		
	}

}
