<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Basket mini line delete. Only lines of the visitor's own current order can be removed.
 */
class order_operations extends \Controller{

	function panel_action($params){

		if (($params['cms_do'] ?? '') == 'delete_order_line'){

			$this->load->model('shop/shop_model');
			$this->load->model('cms/cms_page_panel_model');

			// shop_model::delete_order_line deletes only when the line belongs to this order.
			$order = $this->shop_model->get_current_order_if_any($this->shop_model->get_front_user());
			if (!empty($order['cms_page_panel_id'])){
				$this->shop_model->delete_order_line($order['cms_page_panel_id'], (int)($params['line_id'] ?? 0));
			}

			// Re-render only a basketmini panel
			$params['return_id'] = (int)($params['return_id'] ?? 0);
			$return_panel = $this->cms_page_panel_model->get_cms_page_panel($params['return_id']);
			if (($return_panel['panel_name'] ?? '') !== 'shop/basketmini'){
				$params['return_id'] = 0;
			}

		}

		return $params;

	}

}
