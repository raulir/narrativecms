<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cms_video_encode extends CI_Controller {
	
	function panel_action($params){

		$this->load->model('cms/cms_video_model');
		return $this->cms_video_model->process_encode_queue();

	}

}