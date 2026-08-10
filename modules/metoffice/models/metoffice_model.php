<?php

namespace metoffice;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Met Office DataHub Global Spot provider.
 * Caches raw hourly + three-hourly GeoJSON; converts to weather module standard shape.
 */
class metoffice_model extends \Model {

	const SPOT_BASE = 'https://data.hub.api.metoffice.gov.uk/sitespecific/v0/point';
	const DEFAULT_CACHE_MIN = 180;
	const SOURCE = 'metoffice';
	const ATTRIBUTION = 'Met Office DataHub Global Spot';

	function get_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('metoffice/metoffice');
		return is_array($settings) ? $settings : [];

	}

	/**
	 * Standardized weather_forecast response for weather module.
	 *
	 * @return array{ok:int, error?:string, source?:string, attribution?:string, fetched_at?:int, from_cache?:int, data?:array}
	 */
	function get_forecast_payload($latitude, $longitude, $force_refresh = false){

		$settings = $this->get_settings();
		$api_key = trim((string)($settings['api_key'] ?? ''));
		if ($api_key === ''){
			return ['ok' => 0, 'error' => 'Met Office API key not set', 'source' => self::SOURCE];
		}

		$cache_min = (int)($settings['cache_minutes'] ?? self::DEFAULT_CACHE_MIN);
		if ($cache_min < 5){
			$cache_min = self::DEFAULT_CACHE_MIN;
		}

		$lat = (float)$latitude;
		$lon = (float)$longitude;
		$path = $this->_raw_cache_path($lat, $lon);
		$ttl = max(300, $cache_min * 60);
		$now = time();

		// Fresh raw cache (honour TTL unless force)
		if (!$force_refresh && is_file($path) && ($now - filemtime($path)) < $ttl){
			$raw = $this->_load_raw_cache($path);
			if ($raw !== null){
				$data = $this->_to_standard_shape($raw['hourly'] ?? null, $raw['three_hourly'] ?? null, $lat, $lon);
				if (!empty($data['hourly']['time'])){
					return [
						'ok' => 1,
						'source' => self::SOURCE,
						'attribution' => self::ATTRIBUTION,
						'fetched_at' => (int)filemtime($path),
						'from_cache' => 1,
						'data' => $data,
					];
				}
			}
		}

		// Expired / force / cold: network (stale file is fallback only if request fails)
		$hourly_fc = $this->_get_point('hourly', $lat, $lon, $api_key);
		$three_fc = $this->_get_point('three-hourly', $lat, $lon, $api_key);
		if ($hourly_fc === null && $three_fc === null){
			// Network fail → stale raw if any
			if (is_file($path)){
				$raw = $this->_load_raw_cache($path);
				if ($raw !== null){
					$data = $this->_to_standard_shape($raw['hourly'] ?? null, $raw['three_hourly'] ?? null, $lat, $lon);
					if (!empty($data['hourly']['time'])){
						return [
							'ok' => 1,
							'source' => self::SOURCE,
							'attribution' => self::ATTRIBUTION,
							'fetched_at' => (int)filemtime($path),
							'from_cache' => 1,
							'data' => $data,
						];
					}
				}
			}
			return ['ok' => 0, 'error' => 'Met Office Global Spot request failed', 'source' => self::SOURCE];
		}

		$raw_bundle = [
			'fetched_at' => $now,
			'latitude' => $lat,
			'longitude' => $lon,
			'hourly' => $hourly_fc,
			'three_hourly' => $three_fc,
		];
		@file_put_contents($path, json_encode($raw_bundle, JSON_UNESCAPED_UNICODE));

		$data = $this->_to_standard_shape($hourly_fc, $three_fc, $lat, $lon);
		if (empty($data['hourly']['time'])){
			return ['ok' => 0, 'error' => 'Met Office response empty', 'source' => self::SOURCE];
		}

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

		$key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', 'metoffice_raw_'.sprintf('%.2f_%.2f', $lat, $lon));
		return $GLOBALS['config']['base_path'].'cache/'.$key.'.json';

	}

	/**
	 * @return array|null
	 */
	function _load_raw_cache($path){

		$json = @file_get_contents($path);
		$data = $json !== false ? json_decode($json, true) : null;
		if (!is_array($data)){
			return null;
		}
		// Accept new raw bundle or legacy converted weather_mo_ shape (hourly arrays)
		if (isset($data['hourly']) && is_array($data['hourly']) && !empty($data['hourly']['features'])){
			return $data;
		}
		if (isset($data['hourly']['time']) && is_array($data['hourly']['time'])){
			// Legacy converted cache: wrap as already-standard via fake path
			return null; // handled by caller via separate legacy path if needed
		}
		if (!empty($data['three_hourly']) || !empty($data['hourly'])){
			return $data;
		}
		return null;

	}

	/**
	 * @return array|null GeoJSON feature collection
	 */
	function _get_point($kind, $lat, $lon, $api_key){

		$kind = ($kind === 'three-hourly') ? 'three-hourly' : 'hourly';
		$query = http_build_query([
			'latitude' => $lat,
			'longitude' => $lon,
			'includeLocationName' => 'true',
			'excludeParameterMetadata' => 'true',
		]);
		$url = self::SPOT_BASE.'/'.$kind.'?'.$query;
		$key_h = str_replace(["\r", "\n"], '', (string)$api_key);
		$log_path = $GLOBALS['config']['base_path'].'cache/apis.log';
		// Uniform: <Module> <type> <function> — module capitalised, rest lower-case
		$log_label = ($kind === 'three-hourly') ? 'Metoffice http three-hourly' : 'Metoffice http hourly';
		$ctx = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => 25,
				'header' => "Accept: application/json\r\napikey: ".$key_h."\r\n",
				'ignore_errors' => true,
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			],
		]);
		$body = @file_get_contents($url, false, $ctx);
		if ($body === false || $body === ''){
			@file_put_contents($log_path, date('H:i:s').' '.$log_label." - result fail\n", FILE_APPEND | LOCK_EX);
			return null;
		}
		$data = json_decode($body, true);
		if (!is_array($data) || empty($data['features'][0]['properties']['timeSeries'])){
			@file_put_contents($log_path, date('H:i:s').' '.$log_label." - result empty\n", FILE_APPEND | LOCK_EX);
			return null;
		}
		@file_put_contents($log_path, date('H:i:s').' '.$log_label." - result ok\n", FILE_APPEND | LOCK_EX);
		return $data;

	}

	/**
	 * Open-Meteo-compatible hourly (+ daily extrema) — weather module standard intermediate shape.
	 */
	function _to_standard_shape($hourly_fc, $three_fc, $lat, $lon){

		$h_pts = $this->_series_points($hourly_fc);
		$t_pts = $this->_series_points($three_fc);

		$by_ts = [];
		foreach ($h_pts as $p){
			$by_ts[$p['ts']] = $p;
			$by_ts[$p['ts']]['from_hourly'] = 1;
		}

		$anchors = $t_pts;
		usort($anchors, function($a, $b){
			return $a['ts'] - $b['ts'];
		});

		if (!empty($anchors)){
			$t0 = $anchors[0]['ts'];
			$t1 = $anchors[count($anchors) - 1]['ts'];
			for ($ts = $t0 - ($t0 % 3600); $ts <= $t1; $ts += 3600){
				if (isset($by_ts[$ts]) && !empty($by_ts[$ts]['from_hourly'])){
					continue;
				}
				$blended = $this->_blend_at($anchors, $ts);
				if ($blended !== null){
					$by_ts[$ts] = $blended;
				}
			}
		}

		ksort($by_ts, SORT_NUMERIC);
		$times = [];
		$temps = [];
		$codes = [];
		$pops = [];
		$precs = [];
		$clouds = [];
		$vis = [];
		$winds = [];
		$dirs = [];
		$is_day = [];

		$tz = new \DateTimeZone('Europe/London');
		foreach ($by_ts as $ts => $p){
			$dt = new \DateTime('@'.$ts);
			$dt->setTimezone($tz);
			$times[] = $dt->format('Y-m-d\TH:i');
			$temps[] = $p['temp'];
			$codes[] = $p['code'];
			$pops[] = $p['pop'];
			$precs[] = $p['precip'];
			$clouds[] = $p['cloud'];
			$vis[] = $p['vis'];
			$winds[] = $p['wind_mph'];
			$dirs[] = $p['wind_dir'];
			$is_day[] = $p['is_day'];
		}

		$daily_by = [];
		foreach ($by_ts as $ts => $p){
			$dt = new \DateTime('@'.$ts);
			$dt->setTimezone($tz);
			$d = $dt->format('Y-m-d');
			if (!isset($daily_by[$d])){
				$daily_by[$d] = ['max' => $p['temp'], 'min' => $p['temp'], 'code' => $p['code'], 'pop' => $p['pop']];
			} else {
				if ($p['temp'] > $daily_by[$d]['max']){
					$daily_by[$d]['max'] = $p['temp'];
				}
				if ($p['temp'] < $daily_by[$d]['min']){
					$daily_by[$d]['min'] = $p['temp'];
				}
				if ($p['pop'] > $daily_by[$d]['pop']){
					$daily_by[$d]['pop'] = $p['pop'];
				}
				$h = (int)$dt->format('G');
				if ($h >= 11 && $h <= 14){
					$daily_by[$d]['code'] = $p['code'];
				}
			}
		}
		ksort($daily_by);
		$d_times = [];
		$d_max = [];
		$d_min = [];
		$d_code = [];
		$d_pop = [];
		foreach ($daily_by as $d => $row){
			$d_times[] = $d;
			$d_max[] = $row['max'];
			$d_min[] = $row['min'];
			$d_code[] = $row['code'];
			$d_pop[] = $row['pop'];
		}

		return [
			'latitude' => $lat,
			'longitude' => $lon,
			'timezone' => 'Europe/London',
			'_source' => 'metoffice_global_spot',
			'hourly' => [
				'time' => $times,
				'temperature_2m' => $temps,
				'weather_code' => $codes,
				'precipitation_probability' => $pops,
				'precipitation' => $precs,
				'cloud_cover' => $clouds,
				'visibility' => $vis,
				'wind_speed_10m' => $winds,
				'wind_direction_10m' => $dirs,
				'is_day' => $is_day,
			],
			'daily' => [
				'time' => $d_times,
				'temperature_2m_max' => $d_max,
				'temperature_2m_min' => $d_min,
				'weather_code' => $d_code,
				'precipitation_probability_max' => $d_pop,
			],
		];

	}

	/**
	 * @return list<array>
	 */
	function _series_points($fc){

		$out = [];
		if (!is_array($fc) || empty($fc['features'][0]['properties']['timeSeries'])){
			return $out;
		}
		$series = $fc['features'][0]['properties']['timeSeries'];
		if (!is_array($series)){
			return $out;
		}
		foreach ($series as $row){
			if (!is_array($row) || empty($row['time'])){
				continue;
			}
			$ts = strtotime($row['time']);
			if ($ts === false){
				continue;
			}
			$ts = $ts - ($ts % 3600);
			$temp = null;
			if (isset($row['screenTemperature']) && $row['screenTemperature'] !== null){
				$temp = (float)$row['screenTemperature'];
			} else if (isset($row['maxScreenAirTemp'], $row['minScreenAirTemp'])){
				$temp = ((float)$row['maxScreenAirTemp'] + (float)$row['minScreenAirTemp']) / 2.0;
			} else if (isset($row['maxScreenAirTemp'])){
				$temp = (float)$row['maxScreenAirTemp'];
			}
			if ($temp === null){
				continue;
			}
			$sig = isset($row['significantWeatherCode']) ? (int)$row['significantWeatherCode'] : 7;
			$code = $this->_sig_to_wmo($sig);
			$pop = isset($row['probOfPrecipitation']) ? (float)$row['probOfPrecipitation'] : 0.0;
			$precip = isset($row['totalPrecipAmount']) ? (float)$row['totalPrecipAmount'] : 0.0;
			$cloud = 50.0;
			if ($code === 0){
				$cloud = 5.0;
			} else if ($code <= 2){
				$cloud = 40.0;
			} else if ($code === 3){
				$cloud = 90.0;
			}
			$vis_m = isset($row['visibility']) ? (float)$row['visibility'] : 20000.0;
			$wind_ms = isset($row['windSpeed10m']) ? (float)$row['windSpeed10m'] : 0.0;
			$wind_mph = $wind_ms * 2.2369362921;
			$dir = isset($row['windDirectionFrom10m']) ? (float)$row['windDirectionFrom10m'] : 0.0;
			$is_day = 1;
			if (in_array($sig, [0, 2, 9, 13, 16, 19, 22, 25, 28], true)){
				$is_day = 0;
			}
			$dt = new \DateTime('@'.$ts);
			$dt->setTimezone(new \DateTimeZone('Europe/London'));
			$hloc = (int)$dt->format('G');
			if ($hloc < 6 || $hloc >= 21){
				if (in_array($sig, [1, 3, 10, 14, 17, 20, 23, 26, 29], true)){
					$is_day = 0;
				}
			}

			$out[] = [
				'ts' => $ts,
				'temp' => $temp,
				'code' => $code,
				'pop' => $pop,
				'precip' => $precip,
				'cloud' => $cloud,
				'vis' => $vis_m,
				'wind_mph' => $wind_mph,
				'wind_dir' => $dir,
				'is_day' => $is_day,
				'sig' => $sig,
			];
		}
		return $out;

	}

	/**
	 * Linear blend temp/wind/pop between 3h anchors; sky code from nearest mid-step.
	 */
	function _blend_at(array $anchors, $ts){

		$ts = (int)$ts;
		$n = count($anchors);
		if ($n === 0){
			return null;
		}
		if ($ts <= $anchors[0]['ts']){
			$p = $anchors[0];
			$p['from_hourly'] = 0;
			return $p;
		}
		if ($ts >= $anchors[$n - 1]['ts']){
			$p = $anchors[$n - 1];
			$p['from_hourly'] = 0;
			return $p;
		}
		for ($i = 0; $i < $n - 1; $i++){
			$a = $anchors[$i];
			$b = $anchors[$i + 1];
			if ($ts < $a['ts'] || $ts > $b['ts']){
				continue;
			}
			$span = $b['ts'] - $a['ts'];
			if ($span <= 0){
				$p = $a;
				$p['from_hourly'] = 0;
				return $p;
			}
			if ($ts === $a['ts']){
				$p = $a;
				$p['from_hourly'] = 0;
				return $p;
			}
			$w = ($ts - $a['ts']) / $span;
			$near = ($w < 0.5) ? $a : $b;
			return [
				'ts' => $ts,
				'temp' => (1.0 - $w) * $a['temp'] + $w * $b['temp'],
				'code' => $near['code'],
				'pop' => (1.0 - $w) * $a['pop'] + $w * $b['pop'],
				'precip' => (1.0 - $w) * $a['precip'] + $w * $b['precip'],
				'cloud' => $near['cloud'],
				'vis' => (1.0 - $w) * $a['vis'] + $w * $b['vis'],
				'wind_mph' => (1.0 - $w) * $a['wind_mph'] + $w * $b['wind_mph'],
				'wind_dir' => $near['wind_dir'],
				'is_day' => $near['is_day'],
				'from_hourly' => 0,
			];
		}
		return null;

	}

	function _sig_to_wmo($sig){

		$sig = (int)$sig;
		if ($sig === 0 || $sig === 1){
			return 0;
		}
		if ($sig === 2 || $sig === 3){
			return 2;
		}
		if ($sig === 5 || $sig === 6){
			return 45;
		}
		if ($sig === 7 || $sig === 8){
			return 3;
		}
		if ($sig === 11){
			return 51;
		}
		if (in_array($sig, [9, 10, 12], true)){
			return 61;
		}
		if (in_array($sig, [13, 14, 15], true)){
			return 65;
		}
		if ($sig >= 16 && $sig <= 21){
			return 61;
		}
		if ($sig >= 22 && $sig <= 27){
			return 71;
		}
		if ($sig >= 28 && $sig <= 30){
			return 95;
		}
		return 3;

	}

}
