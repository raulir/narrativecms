<?php

namespace agileforecast;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Agile Forecast (agileforecast.co.uk) price forecast provider.
 * Own file cache of raw/parsed forecast; no energy_history writes.
 */
class agileforecast_model extends \Model {

	const DEFAULT_CACHE_MIN = 120; // 2 h — FE never forces; network only when TTL expires
	const SOURCE = 'agileforecast';

	function get_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('agileforecast/agileforecast');
		return is_array($settings) ? $settings : [];

	}

	/**
	 * Standardized energy_price_forecast payload.
	 */
	function get_forecast_payload($region, $force_refresh = false){

		$region = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$region));
		if ($region === ''){
			return ['ok' => 0, 'source' => self::SOURCE, 'error' => 'Invalid region', 'slots' => []];
		}

		$settings = $this->get_settings();
		$cache_min = (int)($settings['cache_minutes'] ?? self::DEFAULT_CACHE_MIN);
		if ($cache_min < 5){
			$cache_min = self::DEFAULT_CACHE_MIN;
		}
		$ttl = $cache_min * 60;
		$path = $this->_cache_path($region);
		$now = time();

		if (!$force_refresh && is_file($path)){
			$raw = @file_get_contents($path);
			$data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
			if (is_array($data) && !empty($data['by_ts']) && is_array($data['by_ts'])){
				$fetched_at = !empty($data['fetched_at']) ? (int)$data['fetched_at'] : 0;
				if ($fetched_at > 0 && ($now - $fetched_at) < $ttl){
					return $this->_payload_from_by_ts($data['by_ts'], $fetched_at, 1);
				}
			}
		}

		$fetched = $this->_fetch_agile_forecast($region);
		if (empty($fetched['ok']) || empty($fetched['by_ts'])){
			// stale cache fallback
			if (is_file($path)){
				$raw = @file_get_contents($path);
				$data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
				if (is_array($data) && !empty($data['by_ts'])){
					return $this->_payload_from_by_ts(
							$data['by_ts'],
							!empty($data['fetched_at']) ? (int)$data['fetched_at'] : 0,
							1
					);
				}
			}
			return [
				'ok' => 0,
				'source' => self::SOURCE,
				'error' => !empty($fetched['error']) ? $fetched['error'] : 'Forecast request failed',
				'slots' => [],
			];
		}

		$store = [
			'fetched_at' => $now,
			'region' => $region,
			'by_ts' => $fetched['by_ts'],
			'created_at' => $fetched['created_at'] ?? '',
		];
		@file_put_contents($path, json_encode($store, JSON_UNESCAPED_UNICODE));

		return $this->_payload_from_by_ts($fetched['by_ts'], $now, 0);

	}

	function _payload_from_by_ts($by_ts, $fetched_at, $from_cache){

		$slots = [];
		foreach ($by_ts as $k => $v){
			if (!is_array($v) || !isset($v['pred'])){
				continue;
			}
			$ts = (int)$k;
			$ts = $ts - ($ts % 1800);
			$slots[] = [
				'slot_start' => $ts,
				'price_p' => (float)$v['pred'],
				'price_low' => isset($v['low']) ? (float)$v['low'] : (float)$v['pred'],
				'price_high' => isset($v['high']) ? (float)$v['high'] : (float)$v['pred'],
			];
		}
		usort($slots, function($a, $b){
			return $a['slot_start'] <=> $b['slot_start'];
		});

		return [
			'ok' => !empty($slots) ? 1 : 0,
			'source' => self::SOURCE,
			'fetched_at' => (int)$fetched_at,
			'from_cache' => $from_cache ? 1 : 0,
			'slots' => $slots,
			'error' => '',
		];

	}

	function _cache_path($region){

		$safe = preg_replace('/[^A-Za-z]/', '', $region);
		return $GLOBALS['config']['base_path'].'cache/agileforecast_raw_'.$safe.'.json';

	}

	function _fetch_agile_forecast($region){

		$url = 'https://agileforecast.co.uk/api/'.rawurlencode($region);
		$page = $this->_http_get_json($url);
		if (empty($page['ok'])){
			return ['ok' => 0, 'error' => $page['error'], 'by_ts' => [], 'created_at' => ''];
		}

		$body = $page['data'];
		$entry = null;
		if (isset($body[0]) && is_array($body[0])){
			$entry = $body[0];
		} else if (is_array($body) && !empty($body['prices'])){
			$entry = $body;
		}

		if ($entry === null || empty($entry['prices']) || !is_array($entry['prices'])){
			return ['ok' => 0, 'error' => 'No forecast prices', 'by_ts' => [], 'created_at' => ''];
		}

		$by_ts = [];
		foreach ($entry['prices'] as $row){
			if (!is_array($row) || empty($row['date_time'])){
				continue;
			}
			$ts = strtotime($row['date_time']);
			if ($ts === false || $ts <= 0){
				continue;
			}
			if (!isset($row['agile_pred'])){
				continue;
			}
			$pred = (float)$row['agile_pred'];
			$low = isset($row['agile_low']) ? (float)$row['agile_low'] : $pred;
			$high = isset($row['agile_high']) ? (float)$row['agile_high'] : $pred;
			$by_ts[(int)$ts] = [
				'pred' => round($pred, 4),
				'low' => round($low, 4),
				'high' => round($high, 4),
			];
		}

		if (empty($by_ts)){
			return ['ok' => 0, 'error' => 'Empty forecast map', 'by_ts' => [], 'created_at' => ''];
		}

		return [
			'ok' => 1,
			'error' => '',
			'by_ts' => $by_ts,
			'created_at' => !empty($entry['created_at']) ? (string)$entry['created_at'] : '',
		];

	}

	/**
	 * One-line log of a real outbound HTTP call → cache/apis.log
	 */
	function _log_api_call($message){

		$path = $GLOBALS['config']['base_path'].'cache/apis.log';
		$line = date('H:i:s').' '.$message."\n";
		@file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

	}

	function _http_get_json($url){

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => "Accept: application/json\r\n",
				'timeout' => 25,
				'ignore_errors' => true,
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			],
		]);

		$body = @file_get_contents($url, false, $context);
		// Module capitalised; type + function lower-case
		if ($body === false || $body === ''){
			$this->_log_api_call('Agile forecast fetch - result fail');
			return ['ok' => 0, 'error' => 'HTTP request failed', 'data' => null];
		}
		$data = json_decode($body, true);
		if (!is_array($data)){
			$this->_log_api_call('Agile forecast fetch - result empty');
			return ['ok' => 0, 'error' => 'Invalid JSON', 'data' => null];
		}
		$this->_log_api_call('Agile forecast fetch - result ok');
		return ['ok' => 1, 'error' => '', 'data' => $data];

	}

}
