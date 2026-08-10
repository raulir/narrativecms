<?php

namespace octopusenergy;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides service energy_price.
 */
class price extends \Controller {

	function panel_action($params){

		$post = function($key){
			if (!empty($this->input) && is_object($this->input) && method_exists($this->input, 'post')){
				return $this->input->post($key);
			}
			return null;
		};

		$do = $params['do'] ?? $post('do');
		if ($do !== 'energy_price' && $do !== 'price'){
			return $params;
		}

		$this->load->model('octopusenergy/octopusenergy_model');

		$return_result = !empty($params['return_result']) || !empty($post('return_result'));
		$product = $params['product_code'] ?? $post('product_code') ?? '';
		$region = $params['region'] ?? $post('region') ?? '';
		$ws = (int)($params['window_start'] ?? $post('window_start') ?? 0);
		$we = (int)($params['window_end'] ?? $post('window_end') ?? 0);
		$force = !empty($params['force_refresh']) || !empty($post('force_refresh'));

		$result = $this->octopusenergy_model->get_price_payload($product, $region, $ws, $we, $force);

		if ($return_result){
			return is_array($result) ? array_merge($params, $result) : $params;
		}
		print(json_encode($result, JSON_UNESCAPED_UNICODE));
		exit();

	}

	function panel_params($params){

		return $params;

	}

}
