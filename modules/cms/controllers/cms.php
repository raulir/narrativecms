<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms extends CI_Controller {
		
   	function updater($param = '', $second = ''){

   		$this->load->model('cms/cms_update_model');
   		
		if (!empty($param)){
			$do = $param;
		} else {
			$do = $this->input->post('do');
		}
		
        if ($do == 'version'){
        	
   			$version_data = $this->cms_update_model->get_version($this->input->post('module'));
   			print(json_encode($version_data));
   			exit();
        	
        } else if ($do == 'files'){
        	
        	$data = $this->cms_update_model->get_files();
   			print(json_encode($data));
   			exit();

        } else if ($do == 'file'){
        	
			if (!empty($second)){
				$filename = $second;
			} else {
				$filename = $this->input->post('filename');
			}
	        	
        	$data = $this->cms_update_model->get_file($filename);
   			print(json_encode($data));
   			exit();
        	
        }
  	
   	}

}
