<?php

namespace openmeteo;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Open-Meteo forecast provider.
 * Caches raw API JSON; returns weather module standard shape (native OM format).
 */
class openmeteo_model extends \Model {

	const API_BASE = 'https://api.open-meteo.com/v1/forecast';
	const DEFAULT_CACHE_MIN = 180;
	const FORECAST_DAYS = 16;
	const PAST_DAYS = 1;
	const SOURCE = 'open-meteo';
	const ATTRIBUTION = 'Met Office via Open-Meteo';

	function get_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('openmeteo/openmeteo');
		return is_array($settings) ? $settings : [];

	}

	/**
	 * Standardized weather_forecast response for weather module.
	 *
	 * @return array{ok:int, error?:string, source?:string, attribution?:string, fetched_at?:int, from_cache?:int, data?:array}
	 */
	function get_forecast_payload($latitude, $longitude, $force_refresh = false){

		$settings = $this->get_settings();
		$cache_min = (int)($settings['cache_minutes'] ?? self::DEFAULT_CACHE_MIN);
		if ($cache_min < 5){
			$cache_min = self::DEFAULT_CACHE_MIN;
		}
		$models = trim((string)($settings['models'] ?? 'best_match'));
		if ($models === ''){
			$models = 'best_match';
		}

		$lat = (float)$latitude;
		$lon = (float)$longitude;
		$path = $this->_raw_cache_path($lat, $lon);
		$ttl = $cache_min * 60;
		$now = time();

		// Fresh raw cache (honour TTL unless force)
		if (!$force_refresh && is_file($path) && ($now - filemtime($path)) < $ttl){
			$hit = $this->_load_raw_cache($path);
			if ($hit !== null){
				return [
					'ok' => 1,
					'source' => self::SOURCE,
					'attribution' => self::ATTRIBUTION,
					'fetched_at' => (int)filemtime($path),
					'from_cache' => 1,
					'data' => $hit,
				];
			}
		}

		// Expired / force / cold: network (stale file is fallback only if request fails)
		$query = http_build_query([
			'latitude' => $lat,
			'longitude' => $lon,
			'timezone' => 'Europe/London',
			'forecast_days' => self::FORECAST_DAYS,
			'past_days' => self::PAST_DAYS,
			'models' => $models,
			'temperature_unit' => 'celsius',
			'wind_speed_unit' => 'mph',
			'hourly' => implode(',', [
				'temperature_2m',
				'weather_code',
				'precipitation_probability',
				'precipitation',
				'cloud_cover',
				'visibility',
				'wind_speed_10m',
				'wind_direction_10m',
				'is_day',
			]),
			'daily' => implode(',', [
				'weather_code',
				'temperature_2m_max',
				'temperature_2m_min',
				'precipitation_probability_max',
			]),
		]);

		$url = self::API_BASE.'?'.$query;
		$ctx = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => 20,
				'header' => "Accept: application/json\r\n",
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			],
		]);

		$body = @file_get_contents($url, false, $ctx);
		if ($body === false || $body === ''){
			if (is_file($path)){
				$hit = $this->_load_raw_cache($path);
				if ($hit !== null){
					return [
						'ok' => 1,
						'source' => self::SOURCE,
						'attribution' => self::ATTRIBUTION,
						'fetched_at' => (int)filemtime($path),
						'from_cache' => 1,
						'data' => $hit,
					];
				}
			}
			return ['ok' => 0, 'error' => 'Open-Meteo request failed', 'source' => self::SOURCE];
		}

		$data = json_decode($body, true);
		if (!is_array($data) || empty($data['hourly']['time'])){
			$msg = !empty($data['reason']) ? (string)$data['reason'] : 'Invalid forecast response';
			return ['ok' => 0, 'error' => $msg, 'source' => self::SOURCE];
		}

		@file_put_contents($path, $body);
		return [
			'ok' => 1,
			'source' => self::SOURCE,
			'attribution' => self::ATTRIBUTION,
			'fetched_at' => $now,
			'from_cache' => 0,
			'data' => $data,
		];

	}

	function _raw_cache_path($lat, $lon){

		$key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', 'openmeteo_raw_'.sprintf('%.2f_%.2f', $lat, $lon));
		return $GLOBALS['config']['base_path'].'cache/'.$key.'.json';

	}

	/**
	 * @return array|null
	 */
	function _load_raw_cache($path){

		$json = @file_get_contents($path);
		$data = $json !== false ? json_decode($json, true) : null;
		if (!is_array($data) || empty($data['hourly']['time'])){
			return null;
		}
		return $data;

	}

}
