<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Product item list title: product name, plus dim value labels when set.
 */
class product_item extends \Controller {

	function panel_heading($params){

		$this->load->model('shop/shop_dim_model');

		return $this->shop_dim_model->item_list_title($params);

	}

}
