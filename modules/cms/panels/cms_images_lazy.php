<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_images_lazy extends MY_Controller{

	function __construct(){

		parent::__construct();

	}

	function panel_action($params){

		$do = $this->input->post('do');
		if ($do == 'resize'){
			
			$params['output'] = $this->input->post('output');
			$params['width'] = $this->input->post('width');
			$params['width_lq'] = $this->input->post('width_lq');
			$params['image'] = $this->input->post('image');
			
			$image_dir = pathinfo($params['image'], PATHINFO_DIRNAME);
			$image_name = pathinfo($params['image'], PATHINFO_FILENAME);
			
			$params['src'] = $GLOBALS['config']['upload_url'].$image_dir.'/_'.$image_name.'.'.$params['width'].'.'.$params['output'];

		}

		return $params;

	}

}
 