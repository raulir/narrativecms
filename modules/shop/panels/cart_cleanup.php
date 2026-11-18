<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Cron task: delete abandoned draft carts. Register under CMS repeating tasks (daily).
 * Age: Shop settings → Delete carts after (days).
 */
class cart_cleanup extends \Controller {

	function panel_action($params = []){

		$this->load->model('shop/shop_model');

		$days = (int)$this->shop_model->get_shop_settings()['cart_delete_days'];
		if ($days < 1){
			return ['message' => "Cart cleanup is off (Delete carts after = 0)\nnoop"];
		}

		$deleted = $this->shop_model->delete_stale_carts($days);

		return ['message' => $deleted ? 'Deleted '.$deleted.' abandoned carts older than '.$days.' days' : "No abandoned carts to delete\nnoop"];

	}

}
