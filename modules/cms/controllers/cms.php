<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms extends Controller {
		
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
        	
        	$module = $this->input->post('module');
        	
        	$data = $this->cms_update_model->get_files($module);
   			print(json_encode($data));
   			exit();

        } else if ($do == 'file'){
        	
			if (!empty($second)){
				$filename = $second;
			} else {
				$filename = $this->input->post('filename');
			}
			
			$module = $this->input->post('module');
				
        	$data = $this->cms_update_model->get_file($filename, $module);
   			print(json_encode($data));
   			exit();
        	
        }
  	
   	}

}
