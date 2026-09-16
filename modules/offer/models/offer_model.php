<?php

namespace offer;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class offer_model extends \Model {

	function get_offers(){

		$this->load->model('cms/cms_page_panel_model');

		return $this->cms_page_panel_model->get_list('offer/offer');

	}

}
