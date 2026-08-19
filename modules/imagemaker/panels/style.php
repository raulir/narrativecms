<?php

namespace imagemaker;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Admin save: drop productthumb HTML for products that resolve to this style.
 */
class style extends \Controller {

	function on_update($params){

		$id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($id > 0){
			$this->load->model('imagemaker/imagemaker_model');
			$this->imagemaker_model->invalidate_thumbs_for_style($id);
		}

		return $params;

	}

}
