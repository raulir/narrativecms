<?php

namespace imagemaker;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Extends shop/subcategory: invalidate inheriting productthumbs when style FK is saved.
 */
class shop_subcategory extends \Controller {

	function on_update($params){

		$id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($id > 0){
			$this->load->model('imagemaker/imagemaker_model');
			$this->imagemaker_model->invalidate_thumbs_for_subcategory($id);
		}

		return $params;

	}

}
