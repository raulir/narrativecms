<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class article extends CI_Controller{
	
	function panel_params($params){

		// page head data
		$this->title = $params['heading'];
		$this->description = $params['lead_text'];
		if (!empty($params['article_images'][0]['image'])){
			$this->image = $GLOBALS['config']['upload_url'].$params['article_images'][0]['image'];
		}

		return $params;

	}

}
