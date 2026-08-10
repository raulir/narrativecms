<?php

namespace metoffice;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides service weather_forecast.
 * Inputs: latitude, longitude, force_refresh (optional), return_result.
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
		if ($do !== 'forecast' && $do !== 'weather_forecast'){
			return $params;
		}

		$this->load->model('metoffice/metoffice_model');

		$return_result = !empty($params['return_result']) || !empty($post('return_result'));
		$lat = $params['latitude'] ?? $post('latitude');
		$lon = $params['longitude'] ?? $post('longitude');
		$force = !empty($params['force_refresh']) || !empty($post('force_refresh'));

		$result = $this->metoffice_model->get_forecast_payload($lat, $lon, $force);

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
