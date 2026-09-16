<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * List-only shop/order. on_update posts listed stock when status becomes finished.
 */
class order extends \Controller {

	function on_update($params){

		$id = (int)($params['cms_page_panel_id'] ?? 0);
		if ($id <= 0){
			return $params;
		}

		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('shop/shop_model');

		$existing = $this->cms_page_panel_model->get_cms_page_panel($id);
		if ($this->shop_model->order_is_finished($existing) || !empty($existing['stock_posted_time'])){
			$params['status'] = 'finished';
			if (!empty($existing['stock_posted_time'])){
				$params['stock_posted_time'] = $existing['stock_posted_time'];
			}
			return $params;
		}

		if ((string)($params['status'] ?? '') !== 'finished'){
			return $params;
		}

		$this->shop_model->finish_order($id);
		$row = $this->cms_page_panel_model->get_cms_page_panel($id);
		if (!empty($row['stock_posted_time'])){
			$params['stock_posted_time'] = $row['stock_posted_time'];
		}
		$params['status'] = 'finished';

		return $params;

	}

}
