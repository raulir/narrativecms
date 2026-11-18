<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Shop → Orders (admin/orders/). Toolbar and table; rows come from shop/orders_admin_rows.
 */
class orders_admin extends \Controller {

	function panel_params($params){

		$this->load->model('cms/cms_access_model');
		$params['allowed'] = $this->cms_access_model->cms_user_has_access('shop_orders');
		// ?view= opens that filter (the order page's back arrow links to its order's filter)
		$params['view'] = in_array($params['view'] ?? '', ['orders', 'archive', 'carts'], true) ? $params['view'] : 'orders';

		// Rows use the CMS list row look (tick, row, hover) without the rest of the list page.
		add_css('modules/cms/css/cms_list.scss');
		add_css('modules/cms/css/cms_list_list.scss');

		return $params;

	}

}
