<?php

namespace agileforecast;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides service energy_price_forecast.
 */
class forecast extends \Controller {

	function panel_action($params){

		$post = function($key){
			if (!empty($this->input) && is_object($this->input) && method_exists($this->input, 'post')){
				return $this->input->post($key);
			}
			return null;
		};

		$do = $params['do'] ?? $post('do');
		if ($do !== 'energy_price_forecast' && $do !== 'forecast'){
			return $params;
		}

		$this->load->model('agileforecast/agileforecast_model');

		$return_result = !empty($params['return_result']) || !empty($post('return_result'));
		$region = $params['region'] ?? $post('region') ?? '';
		$force = !empty($params['force_refresh']) || !empty($post('force_refresh'));

		$result = $this->agileforecast_model->get_forecast_payload($region, $force);

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
