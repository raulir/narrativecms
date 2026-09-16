<?php

namespace shop;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class productdims extends \Controller{

	function panel_action($params){

		$this->load->model('shop/shop_model');
		$this->load->model('cms/cms_page_panel_model');

		$do = $this->input->post('do');

		if ($do == 'availability'){

			$params['product_id'] = $this->input->post('product_id');
			$params['name'] = $this->input->post('name');
			$params['value'] = $this->input->post('value');

			$params['variations'] = $this->shop_model->get_product_variations($params['product_id'], [
					'dims' => [$params['name'] => $params['value']],
			]);

			print(json_encode(['data' => $params['variations']], JSON_PRETTY_PRINT));
			die();

		}

		return $params;

	}

	function panel_params($params){

		$this->load->model('shop/shop_model');
		$this->load->model('shop/shop_dim_model');
		$this->load->model('cms/cms_page_panel_model');

		$params['product'] = $this->cms_page_panel_model->get_cms_page_panel($params['product_id']);
		$params['variations'] = $this->shop_model->get_product_variations($params['product_id']);
		if (empty($params['variations'])){
			$params['one_size'] = true;
		}

		$params['dim_labels'] = [];
		foreach ($params['variations'] as $key => $data){
			$params['dim_labels'][$key] = $this->shop_model->get_dim_label($key);
		}

		return $params;

	}

}
