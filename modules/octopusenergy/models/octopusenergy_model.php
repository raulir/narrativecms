<?php

namespace octopusenergy;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Octopus Energy provider: official Agile prices + meter usage (Mini/REST). Own file caches only; no energy_history writes.
 */
class octopusenergy_model extends \Model {

	// GraphQL Mini poll (main: last sample→now; then optional ≤1h backfill)
	const USAGE_POLL_MIN_SEC = 120;          // min seconds between Mini GraphQL polls (2 min)
	const USAGE_MAIN_MAX_SEC = 3600;         // hard cap on main + backfill window length
	const USAGE_GAP_FILL_MAX_SEC = 3600;     // step 2: max backward gap window
	const USAGE_OVERLAP_SEC = 60;            // small inclusive overlap (re-merge)
	const USAGE_GAP_FILL_MIN_SEC = 60;       // default min gap between Mini backfills (CMS: gap_fill_min_sec)
	const USAGE_RATE_BACKOFF_SEC = 300;      // cool-down after KT-CT-1199 / too many requests (telemetry)
	const USAGE_AUTH_BACKOFF_SEC = 300;      // cool-down after obtainKrakenToken fail / reauth exhausted
	const GRAPHQL_TOKEN_TTL_SEC = 1800;      // reuse JWT across HTTP requests (30 min)
	const USAGE_REST_MIN_SEC = 1800;         // REST consumption network min gap (30 min)
	const USAGE_REST_MAX_FETCH_SEC = 21600;  // max REST ask window per call (6 h from last tip forward)
	const USAGE_SAMPLES_KEEP_SEC = 259200;   // keep raw Mini samples ~3d
	const USAGE_HALF_KEEP_SEC = 1209600;     // keep half-hours ~14d (until DB persistence)
	const USAGE_REST_FALLBACK_LAG_SEC = 172800; // if no REST yet, don't GraphQL older than ~48h
	// Panel display: use cache without network when younger than this (API does heavy poll)
	const DISPLAY_CACHE_MAX_AGE_SEC = 900;
	// Menu chip: drop newest TEN_SECONDS sample, average next N
	const CURRENT_DEMAND_SAMPLES = 6;

	/**
	 * Live demand chip from Mini sample cache (no network).
	 * Drop newest sample (may be partial window), average up to 6 prior TEN_SECONDS points.
	 * Format 3 significant figures: "345 W" or "2.24 kW".
	 *
	 * @return string empty if unavailable
	 */
	function get_current_demand_display(){

		$samples = $this->_load_any_mini_samples();
		if (empty($samples)){
			return '';
		}

		$ts_list = array_map('intval', array_keys($samples));
		rsort($ts_list, SORT_NUMERIC);

		// Drop newest (possibly incomplete 10s window)
		array_shift($ts_list);
		if (empty($ts_list)){
			return '';
		}

		$take = array_slice($ts_list, 0, self::CURRENT_DEMAND_SAMPLES);
		$sum = 0.0;
		$n = 0;
		foreach ($take as $ts){
			$key = (string)$ts;
			if (!isset($samples[$key]) && !isset($samples[$ts])){
				continue;
			}
			$w = isset($samples[$key]) ? (float)$samples[$key] : (float)$samples[$ts];
			if ($w < 0){
				continue;
			}
			$sum += $w;
			$n++;
		}
		if ($n < 1){
			return '';
		}

		$mean_w = $sum / $n;
		return $this->_format_demand_3sf($mean_w);

	}

	/**
	 * @return array<string,float> ts => demand_w
	 */
	function _load_any_mini_samples(){

		$dir = $GLOBALS['config']['base_path'].'cache/';
		$files = glob($dir.'energy_usage_mini_*.json');
		if (empty($files) || !is_array($files)){
			return [];
		}
		// Prefer newest file
		usort($files, function($a, $b){
			return filemtime($b) - filemtime($a);
		});
		$path = $files[0];
		$raw = @file_get_contents($path);
		if ($raw === false || $raw === ''){
			return [];
		}
		$data = cms_json_decode($raw, $path);
		if (!is_array($data) || empty($data['samples']) || !is_array($data['samples'])){
			return [];
		}
		$out = [];
		foreach ($data['samples'] as $k => $w){
			$out[(string)(int)$k] = (float)$w;
		}
		return $out;

	}

	/**
	 * 3 significant figures with trailing zeros forced (always 3 digit characters).
	 * Under 1000 W → watts; else kW. e.g. "345 W", "45.0 W", "2.20 kW".
	 */
	function _format_demand_3sf($mean_w){

		$mean_w = (float)$mean_w;
		if ($mean_w < 0){
			$mean_w = 0;
		}

		if ($mean_w < 1000){
			return $this->_three_sig_figs_fixed($mean_w).' W';
		}

		return $this->_three_sig_figs_fixed($mean_w / 1000.0).' kW';

	}

	/**
	 * Exactly 3 significant figures, keep trailing zeros (2.20 not 2.2).
	 */
	function _three_sig_figs_fixed($n){

		$n = (float)$n;
		if ($n == 0.0){
			return '0.00';
		}
		$neg = $n < 0;
		$n = abs($n);
		$exp = (int)floor(log10($n));
		// decimals so that 3 s.f. show: 345→0, 45.0→1, 4.50→2, 0.450→3
		$decimals = 2 - $exp;
		if ($decimals < 0){
			$decimals = 0;
		}
		if ($decimals > 6){
			$decimals = 6;
		}
		$factor = pow(10, $decimals);
		$rounded = round($n * $factor) / $factor;
		// Recompute exp after round (e.g. 999.5 → 1000)
		if ($rounded > 0){
			$exp2 = (int)floor(log10($rounded));
			$decimals = 2 - $exp2;
			if ($decimals < 0){
				$decimals = 0;
			}
			if ($decimals > 6){
				$decimals = 6;
			}
		}
		$s = number_format($rounded, $decimals, '.', '');
		return $neg ? '-'.$s : $s;

	}

	function get_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('octopusenergy/octopusenergy');
		return is_array($settings) ? $settings : [];

	}

	/**
	 * Min seconds between Mini GraphQL gap-fill attempts (pink usage backfill).
	 * CMS field gap_fill_min_sec; default USAGE_GAP_FILL_MIN_SEC (60). Clamped 30–3600.
	 */
	function get_gap_fill_min_sec(){

		$settings = $this->get_settings();
		$raw = isset($settings['gap_fill_min_sec']) ? trim((string)$settings['gap_fill_min_sec']) : '';
		if ($raw === '' || !is_numeric($raw)){
			return self::USAGE_GAP_FILL_MIN_SEC;
		}
		$n = (int)$raw;
		if ($n < 30){
			return 30;
		}
		if ($n > 3600){
			return 3600;
		}
		return $n;

	}

	/**
	 * Standardized energy_price payload for energy module.
	 *
	 * @return array{ok:int,source:string,fetched_at?:int,from_cache?:int,max_valid_to?:int,new_prices?:int,slots?:list,error?:string}
	 */
	function get_price_payload($product_code, $region, $window_start = 0, $window_end = 0, $force_refresh = false){

		$product_code = $this->_normalise_product_code($product_code);
		$region = $this->_normalise_region($region);
		if ($product_code === '' || $region === ''){
			return ['ok' => 0, 'source' => 'octopus', 'error' => 'Invalid product code or region', 'slots' => []];
		}

		$tariff_code = 'E-1R-'.$product_code.'-'.$region;
		$now = time();
		if ($window_start > 0 && $window_end > $window_start){
			$window = ['start_ts' => (int)$window_start, 'end_ts' => (int)$window_end];
		} else {
			$window = $this->_graph_window($now);
		}

		// Always 'refresh' so Agile rates may network under _cache_needs_refresh TTL.
		// (Old bug: non-force → 'display' → any rates file cache → never network → official line froze;
		//  same class of bug as usage display-mode freeze.) force_refresh bypasses rates TTL.
		$rates_result = $this->_get_octopus_rates(
				$product_code,
				$tariff_code,
				$window,
				'refresh',
				(bool)$force_refresh
		);
		$rates = !empty($rates_result['rates']) ? $rates_result['rates'] : [];
		$slots = [];
		foreach ($rates as $row){
			if (!is_array($row) || empty($row['ts_from'])){
				continue;
			}
			$slots[] = [
				'slot_start' => (int)$row['ts_from'] - ((int)$row['ts_from'] % 1800),
				'price_p' => isset($row['value_inc_vat']) ? (float)$row['value_inc_vat'] : null,
				'valid_to' => !empty($row['ts_to']) ? (int)$row['ts_to'] : 0,
			];
		}

		return [
			'ok' => !empty($slots) || !empty($rates_result['ok']) ? (empty($slots) ? 0 : 1) : 0,
			'source' => 'octopus',
			'fetched_at' => !empty($rates_result['fetched_at']) ? (int)$rates_result['fetched_at'] : 0,
			'from_cache' => !empty($rates_result['from_cache']) ? 1 : 0,
			'max_valid_to' => !empty($rates_result['max_valid_to']) ? (int)$rates_result['max_valid_to'] : 0,
			'new_prices' => !empty($rates_result['new_prices']) ? 1 : 0,
			'slots' => $slots,
			'error' => !empty($rates_result['error']) ? (string)$rates_result['error'] : '',
			'product_code' => $product_code,
			'region' => $region,
			'tariff_code' => $tariff_code,
		];

	}

	/**
	 * Standardized energy_usage payload. Credentials from Octopus settings.
	 *
	 * @return array{ok:int,source:string,updated_at?:int,slots?:list,demand_w?:float|null,demand_display?:string,error?:string}
	 */
	function get_usage_payload($window_start = 0, $window_end = 0, $force_refresh = false){

		$settings = $this->get_settings();
		$api_key = trim((string)($settings['api_key'] ?? ''));
		$mpan = $settings['mpan'] ?? '';
		$meter_serial = $settings['meter_serial'] ?? '';
		$account_number = $settings['account_number'] ?? '';
		$device_id = $settings['device_id'] ?? '';

		$now = time();
		if ($window_start > 0 && $window_end > $window_start){
			$window = ['start_ts' => (int)$window_start, 'end_ts' => (int)$window_end];
		} else {
			$window = $this->_graph_window($now);
		}

		// Always 'refresh' so Mini/REST may network under their own TTLs
		// (USAGE_POLL_MIN_SEC / USAGE_REST_MIN_SEC). force_refresh bypasses those mins.
		// (Old bug: non-force → 'display' → zero network → pink line froze for hours.)
		$usage = $this->get_usage_kw_series(
				$api_key,
				$mpan,
				$meter_serial,
				$window,
				$account_number,
				$device_id,
				'refresh',
				(bool)$force_refresh
		);

		$series = !empty($usage['series']) && is_array($usage['series']) ? $usage['series'] : [];
		$slots = [];
		$now_slot = $now - ($now % 1800);
		foreach ($series as $row){
			if (!is_array($row) || !isset($row['ts_from'])){
				continue;
			}
			$ts = (int)$row['ts_from'];
			$ts = $ts - ($ts % 1800);
			$src = !empty($row['source']) ? (string)$row['source'] : 'mini';
			if ($src !== 'rest'){
				$src = 'mini';
			}
			$kw = null;
			if (isset($row['kw']) && $row['kw'] !== null){
				$kw = (float)$row['kw'];
			}
			$slots[] = [
				'slot_start' => $ts,
				'usage_kw' => $kw,
				'usage_source' => $src,
				'is_open' => (!empty($row['partial']) || $ts === $now_slot) ? 1 : 0,
			];
		}

		$demand_display = $this->get_current_demand_display();

		return [
			'ok' => !empty($slots) ? 1 : 0,
			'source' => 'octopus',
			'updated_at' => !empty($usage['updated_at']) ? (int)$usage['updated_at'] : 0,
			'from_cache' => $force_refresh ? 0 : 1,
			'slots' => $slots,
			'demand_display' => $demand_display,
			'error' => '',
		];

	}

	function _format_london_hi($ts){

		$ts = (int)$ts;
		if ($ts <= 0){
			return '';
		}
		try {
			$dt = new \DateTime('@'.$ts);
			$dt->setTimezone(new \DateTimeZone('Europe/London'));
			return $dt->format('H:i');
		} catch (\Exception $e) {
			return date('H:i', $ts);
		}

	}

	/**
	 * Octopus rates with existing cache / publish-window refresh.
	 *
	 * @param string $mode display|refresh
	 * @param bool $force_refresh when true with refresh, bypass rates TTL and re-fetch
	 */
	function _get_octopus_rates($product_code, $tariff_code, $window, $mode = 'display', $force_refresh = false){

		$region = substr($tariff_code, -1);
		$cache_path = $this->_cache_path($product_code, $region);
		$cached = $this->_read_cache($cache_path);
		$force_refresh = (bool)$force_refresh;

		// Display: never block page on Octopus — serve cache if present
		if ($mode === 'display' && $cached !== false && !empty($cached['rates'])){
			return [
				'ok' => 1,
				'rates' => $cached['rates'],
				'fetched_at' => (int)$cached['fetched_at'],
				'max_valid_to' => (int)$cached['max_valid_to'],
				'from_cache' => 1,
				'new_prices' => 0,
				'error' => '',
			];
		}

		// Refresh (or cold display bootstrap): honour normal TTL unless force
		if ($cached !== false && !$force_refresh && !$this->_cache_needs_refresh($cached)){
			return [
				'ok' => 1,
				'rates' => $cached['rates'],
				'fetched_at' => (int)$cached['fetched_at'],
				'max_valid_to' => (int)$cached['max_valid_to'],
				'from_cache' => 1,
				'new_prices' => 0,
				'error' => '',
			];
		}

		$previous_max = !empty($cached['max_valid_to']) ? (int)$cached['max_valid_to'] : 0;
		$period_from = $window['start_ts'] - (24 * 3600);
		$period_to = $window['end_ts'];
		$fetched = $this->_fetch_rates($product_code, $tariff_code, $period_from, $period_to);

		if (empty($fetched['ok'])){
			if ($cached !== false && !empty($cached['rates'])){
				return [
					'ok' => 1,
					'rates' => $cached['rates'],
					'fetched_at' => (int)$cached['fetched_at'],
					'max_valid_to' => (int)$cached['max_valid_to'],
					'from_cache' => 1,
					'new_prices' => 0,
					'error' => $fetched['error'],
				];
			}
			return [
				'ok' => 0,
				'rates' => [],
				'fetched_at' => 0,
				'max_valid_to' => 0,
				'from_cache' => 0,
				'new_prices' => 0,
				'error' => $fetched['error'],
			];
		}

		$new_prices = ($previous_max > 0 && (int)$fetched['max_valid_to'] > $previous_max) ? 1 : 0;
		$store = [
			'ok' => 1,
			'rates' => $fetched['rates'],
			'product_code' => $product_code,
			'region' => $region,
			'tariff_code' => $tariff_code,
			'fetched_at' => time(),
			'max_valid_to' => (int)$fetched['max_valid_to'],
			'error' => '',
		];
		$this->_write_cache($cache_path, $store);

		return [
			'ok' => 1,
			'rates' => $fetched['rates'],
			'fetched_at' => $store['fetched_at'],
			'max_valid_to' => $store['max_valid_to'],
			'from_cache' => 0,
			'new_prices' => $new_prices,
			'error' => '',
		];

	}

	function _graph_window($now){

		try {
			$tz = new \DateTimeZone('Europe/London');
			$dt = new \DateTime('@'.(int)$now);
			$dt->setTimezone($tz);

			$limit = clone $dt;
			$limit->modify('-12 hours');

			$hour = (int)$limit->format('G');
			$slot_h = intdiv($hour, 6) * 6;
			$start = clone $limit;
			$start->setTime($slot_h, 0, 0);

			// Floor to previous 6h boundary so at least 12h is visible
			if ($start->getTimestamp() > $limit->getTimestamp()){
				$start->modify('-6 hours');
			}

			$end = clone $start;
			$end->modify('+60 hours');

			return [
				'start_ts' => $start->getTimestamp(),
				'end_ts' => $end->getTimestamp(),
			];
		} catch (\Exception $e) {
			$period_from = $now - ($now % 1800) - (12 * 3600);
			$h = (int)gmdate('G', $period_from);
			$slot = intdiv($h, 6) * 6;
			$start = gmmktime($slot, 0, 0, (int)gmdate('n', $period_from), (int)gmdate('j', $period_from), (int)gmdate('Y', $period_from));
			if ($start > $period_from){
				$start -= 6 * 3600;
			}
			return [
				'start_ts' => $start,
				'end_ts' => $start + (60 * 3600),
			];
		}

	}

	/**
	 * Build series: official Octopus → Agile Forecast pred → 24h-ago official fill.
	 */
	function _build_series($rates, $forecast_by_ts, $window){

		$by_from = [];
		if (is_array($rates)){
			foreach ($rates as $row){
				if (!empty($row['ts_from'])){
					$by_from[(int)$row['ts_from']] = $row;
				}
			}
		}

		if (!is_array($forecast_by_ts)){
			$forecast_by_ts = [];
		}

		$series = [];
		$start = (int)$window['start_ts'];
		$end = (int)$window['end_ts'];

		for ($ts = $start; $ts < $end; $ts += 1800){

			$source = '';
			$value = null;
			$low = null;
			$high = null;

			if (isset($by_from[$ts]) && array_key_exists('value_inc_vat', $by_from[$ts])){
				$value = (float)$by_from[$ts]['value_inc_vat'];
				$source = 'official';
			} else if (isset($forecast_by_ts[$ts])){
				$fc = $forecast_by_ts[$ts];
				$value = (float)$fc['pred'];
				$low = (float)$fc['low'];
				$high = (float)$fc['high'];
				$source = 'forecast';
			} else {
				$prev = $ts - 86400;
				if (isset($by_from[$prev]) && array_key_exists('value_inc_vat', $by_from[$prev])){
					$value = (float)$by_from[$prev]['value_inc_vat'];
					$source = 'filled';
				}
			}

			if ($value === null || $source === ''){
				continue;
			}

			$point = [
				'ts_from' => $ts,
				'ts_to' => $ts + 1800,
				'value_inc_vat' => round($value, 4),
				'source' => $source,
			];

			if ($source === 'forecast'){
				$point['value_low'] = round($low, 4);
				$point['value_high'] = round($high, 4);
			}

			$series[] = $point;

		}

		return $series;

	}

	/**
	 * Half-hour average kW for graph window.
	 * Mini GraphQL fills recent (live-ish); REST half-hours overwrite as billing truth when available.
	 *
	 * @param string $mode display = cache only (no GraphQL/REST network); refresh = may poll
	 * @param bool $force_refresh when true with refresh, bypass Mini/REST poll minimums
	 * @return array{series:list,updated_at:int}
	 */
	function get_usage_kw_series($api_key, $mpan, $meter_serial, $window, $account_number = '', $device_id = '', $mode = 'display', $force_refresh = false){

		$empty = ['series' => [], 'updated_at' => 0];
		$mode = ($mode === 'refresh') ? 'refresh' : 'display';
		$allow_network = ($mode === 'refresh');
		$force_refresh = (bool)$force_refresh;

		$api_key = trim((string)$api_key);
		$mpan = preg_replace('/\D/', '', (string)$mpan);
		$meter_serial = strtoupper(trim((string)$meter_serial));
		$account_number = strtoupper(trim((string)$account_number));
		$device_id = trim((string)$device_id);

		if ($api_key === ''){
			return $empty;
		}

		if (empty($window['start_ts']) || empty($window['end_ts'])){
			return $empty;
		}

		$now = time();
		$win_start = (int)$window['start_ts'];
		$win_end = (int)$window['end_ts'];

		// Resolve device + load accumulate cache (discovery may network only when refreshing)
		$device_id = $this->_resolve_device_id(
				$api_key,
				$account_number,
				$mpan,
				$meter_serial,
				$device_id,
				$allow_network
		);
		if ($device_id === ''){
			// Still try REST-only series if meter known
			$rest_only = $this->_rest_half_hours_map(
					$api_key,
					$mpan,
					$meter_serial,
					$win_start,
					$now,
					$allow_network,
					$force_refresh
			);
			$series = $this->_usage_series_from_half_hours($rest_only, $win_start, $win_end);
			$updated = 0;
			foreach ($series as $row){
				if ($row['ts_to'] > $updated){
					$updated = (int)$row['ts_to'];
				}
			}
			return ['series' => $series, 'updated_at' => $updated];
		}

		$cache_path = $this->_mini_cache_path($device_id);
		$cache = $this->_load_mini_cache($cache_path, $device_id);

		// REST map + frontier for GraphQL floor (see _rest_coverage_frontier)
		$rest_map = [];
		$rest_seed_from = min($win_start, $now - self::USAGE_REST_FALLBACK_LAG_SEC);
		if ($mpan !== '' && $meter_serial !== ''){
			$rest_map = $this->_rest_half_hours_map(
					$api_key,
					$mpan,
					$meter_serial,
					$rest_seed_from,
					$now,
					$allow_network,
					$force_refresh
			);
		}
		// Merge REST tips already on mini half_hours into frontier calc (same map shape)
		$rest_for_frontier = $rest_map;
		if (!empty($cache['half_hours']) && is_array($cache['half_hours'])){
			foreach ($cache['half_hours'] as $k => $row){
				$src = is_array($row) ? ($row['source'] ?? '') : '';
				if ($src !== 'rest'){
					continue;
				}
				$rest_for_frontier[(int)$k] = 1;
			}
		}
		$rest_frontier = $this->_rest_coverage_frontier($rest_for_frontier, $win_start, $now);

		// GraphQL only on refresh path (background API)
		if ($allow_network){
			$this->_maybe_poll_mini($api_key, $device_id, $cache, $cache_path, $rest_frontier, $force_refresh);
		}

		// REST billing truth overwrites Mini half_hours when present
		if (!empty($rest_map)){
			if (empty($cache['half_hours']) || !is_array($cache['half_hours'])){
				$cache['half_hours'] = [];
			}
			foreach ($rest_map as $ts => $kw){
				$cache['half_hours'][(string)(int)$ts] = [
					'kw' => (float)$kw,
					'source' => 'rest',
				];
			}
			if ($allow_network){
				$this->_prune_mini_cache($cache, $now);
				$this->_save_mini_cache($cache_path, $cache);
			}
		}

		$series = $this->_usage_series_from_cache_half_hours($cache, $win_start, $win_end);
		$updated = !empty($cache['last_fetch']) ? (int)$cache['last_fetch'] : 0;
		$last_sample = $this->_cache_last_sample_ts($cache);
		if ($last_sample > $updated){
			$updated = $last_sample;
		}

		return ['series' => $series, 'updated_at' => $updated];

	}

	/**
	 * GraphQL must not request times REST already covers — but only when REST is a real
	 * historical band, not a thin recent island (which used to set frontier ≈ now and
	 * blocked Mini gap-fill, leaving only a few hours of pink line).
	 *
	 * @param array<int|string,mixed> $rest_keys map of half-hour starts (values ignored)
	 * @return int unix floor for GraphQL windows
	 */
	function _rest_coverage_frontier($rest_keys, $win_start, $now){

		$now = (int)$now;
		$win_start = (int)$win_start;
		$fallback = $now - self::USAGE_REST_FALLBACK_LAG_SEC;
		if (empty($rest_keys) || !is_array($rest_keys)){
			return $fallback;
		}

		$slots = [];
		foreach (array_keys($rest_keys) as $k){
			$slots[(int)$k] = 1;
		}
		if (empty($slots)){
			return $fallback;
		}

		$newest = 0;
		foreach (array_keys($slots) as $t){
			if ($t > $newest){
				$newest = $t;
			}
		}
		// Walk back while previous half-hour exists (continuous REST island ending at newest)
		$cont_start = $newest;
		while (isset($slots[$cont_start - 1800])){
			$cont_start -= 1800;
		}
		$newest_end = $newest + 1800;

		// Healthy: continuous REST reaches near the graph window start → Mini only after tip
		if ($cont_start <= $win_start + 6 * 3600){
			return $newest_end;
		}

		// Thin / recent-only island: allow GraphQL to fill *before* the island
		return $cont_start;

	}

	function _mini_cache_path($device_id){

		$safe = preg_replace('/[^A-Za-z0-9\-]/', '_', $device_id);
		return $GLOBALS['config']['base_path'].'cache/energy_usage_mini_'.$safe.'.json';

	}

	function _load_mini_cache($path, $device_id){

		$empty = [
			'device_id' => $device_id,
			'last_fetch' => 0,
			'samples' => [],
			'half_hours' => [],
		];

		if (!is_file($path)){
			return $empty;
		}

		$raw = @file_get_contents($path);
		if ($raw === false || $raw === ''){
			return $empty;
		}

		$data = cms_json_decode($raw, $path);
		if (!is_array($data)){
			return $empty;
		}

		$data['device_id'] = $device_id;
		if (empty($data['samples']) || !is_array($data['samples'])){
			$data['samples'] = [];
		}
		if (empty($data['half_hours']) || !is_array($data['half_hours'])){
			$data['half_hours'] = [];
		}
		if (empty($data['last_fetch'])){
			$data['last_fetch'] = 0;
		}

		return $data;

	}

	function _save_mini_cache($path, $data){

		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		if ($json === false){
			return;
		}
		// Prefer locked write so concurrent polls do not clobber each other
		$fp = @fopen($path, 'c+');
		if ($fp === false){
			@file_put_contents($path, $json);
			return;
		}
		if (@flock($fp, LOCK_EX)){
			ftruncate($fp, 0);
			rewind($fp);
			fwrite($fp, $json);
			fflush($fp);
			flock($fp, LOCK_UN);
		} else {
			@file_put_contents($path, $json);
		}
		fclose($fp);

	}

	/**
	 * Atomic gap-fill claim on mini cache file (acts as short lock).
	 * Under LOCK_EX: re-read → compare last_gap_fill → write now → unlock.
	 * Prevents two concurrent refresh_energy processes both GraphQL-ing the same window.
	 *
	 * @param array $cache in-memory cache (updated last_gap_fill on success)
	 * @return bool true = this process should poll gap-fill
	 */
	function _claim_gap_fill_slot($path, $device_id, $now, $gap_min, &$cache){

		$now = (int)$now;
		$gap_min = max(1, (int)$gap_min);
		$dir = dirname($path);
		if (!is_dir($dir)){
			@mkdir($dir, 0755, true);
		}

		$fp = @fopen($path, 'c+');
		if ($fp === false){
			// Fallback: in-memory only (should be rare)
			$last = !empty($cache['last_gap_fill']) ? (int)$cache['last_gap_fill'] : 0;
			if ($last > 0 && ($now - $last) < $gap_min){
				return false;
			}
			$cache['last_gap_fill'] = $now;
			return true;
		}

		if (!@flock($fp, LOCK_EX)){
			fclose($fp);
			return false;
		}

		$raw = stream_get_contents($fp);
		$data = null;
		if ($raw !== false && $raw !== ''){
			$tmp = json_decode($raw, true);
			if (is_array($tmp)){
				$data = $tmp;
			}
		}
		if (!is_array($data)){
			// Seed from in-memory cache if file empty/corrupt
			$data = is_array($cache) ? $cache : [];
		}

		$last = !empty($data['last_gap_fill']) ? (int)$data['last_gap_fill'] : 0;
		if ($last > 0 && ($now - $last) < $gap_min){
			$cache['last_gap_fill'] = $last;
			flock($fp, LOCK_UN);
			fclose($fp);
			return false;
		}

		$data['device_id'] = $device_id;
		$data['last_gap_fill'] = $now;
		if (empty($data['samples']) || !is_array($data['samples'])){
			$data['samples'] = !empty($cache['samples']) && is_array($cache['samples']) ? $cache['samples'] : [];
		}
		if (empty($data['half_hours']) || !is_array($data['half_hours'])){
			$data['half_hours'] = !empty($cache['half_hours']) && is_array($cache['half_hours']) ? $cache['half_hours'] : [];
		}

		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		if ($json !== false){
			ftruncate($fp, 0);
			rewind($fp);
			fwrite($fp, $json);
			fflush($fp);
		}

		$cache['last_gap_fill'] = $now;
		flock($fp, LOCK_UN);
		fclose($fp);
		return true;

	}

	function _prune_mini_cache(&$cache, $now){

		$sample_cut = $now - self::USAGE_SAMPLES_KEEP_SEC;
		$half_cut = $now - self::USAGE_HALF_KEEP_SEC;

		if (!empty($cache['samples']) && is_array($cache['samples'])){
			foreach (array_keys($cache['samples']) as $k){
				if ((int)$k < $sample_cut){
					unset($cache['samples'][$k]);
				}
			}
		}

		if (!empty($cache['half_hours']) && is_array($cache['half_hours'])){
			foreach (array_keys($cache['half_hours']) as $k){
				if ((int)$k < $half_cut){
					unset($cache['half_hours'][$k]);
				}
			}
		}

	}

	/**
	 * Mini GraphQL poll — two steps (after 2 min throttle / rate-limit backoff):
	 *
	 *  1) Main: last mini-cache sample → now (small overlap, hard cap ≤1h, REST frontier).
	 *  2) Gap-fill: always attempted after main — walks ≤1h backward toward REST when a hole
	 *     remains (internal: ≥5 min between fills, points budget, frontier floor).
	 *
	 * JWT is disk-cached (30 min); auth failures cool down separately (see _graphql_token).
	 *
	 * @param bool $force_refresh bypass USAGE_POLL_MIN_SEC (still honours rate-limit / auth backoff)
	 */
	function _maybe_poll_mini($api_key, $device_id, &$cache, $cache_path, $rest_frontier, $force_refresh = false){

		$now = time();
		$last_fetch = !empty($cache['last_fetch']) ? (int)$cache['last_fetch'] : 0;
		if (!$force_refresh && $last_fetch > 0 && ($now - $last_fetch) < self::USAGE_POLL_MIN_SEC){
			return;
		}

		// Cool-down after Octopus "Too many requests" (request pressure; points may still remain)
		$backoff_until = !empty($cache['graphql_backoff_until']) ? (int)$cache['graphql_backoff_until'] : 0;
		if ($backoff_until > $now){
			return;
		}

		// Global auth cool-down (obtainKrakenToken / reauth) — avoid login spam across requests
		if ($this->_graphql_auth_backoff_active()){
			return;
		}

		// Ensure we can obtain/reuse a JWT before Mini work (no network if disk token valid)
		$token = $this->_graphql_token($api_key);
		if ($token === ''){
			return;
		}

		$rest_frontier = (int)$rest_frontier;

		// ── Step 1: main live fill (last sample → now) ────────────────────
		$recent = $this->_poll_recent_telemetry($api_key, $device_id, $cache, $now, $rest_frontier);
		if (!empty($recent['rate_limited'])){
			// Do not advance last_fetch as “success”; only backoff so we retry after cool-down
			$cache['graphql_backoff_until'] = $now + self::USAGE_RATE_BACKOFF_SEC;
			$cache['graphql_last_error'] = !empty($recent['error']) ? $recent['error'] : 'Too many requests';
			$this->_save_mini_cache($cache_path, $cache);
			return;
		}
		if (!empty($recent['auth_failed'])){
			$cache['graphql_last_error'] = !empty($recent['error']) ? $recent['error'] : 'Auth failed';
			$this->_save_mini_cache($cache_path, $cache);
			return;
		}

		// Main OK (even if 0 new rows) — clear prior rate-limit error
		unset($cache['graphql_backoff_until'], $cache['graphql_last_error']);

		// ── Step 2: always try gap-fill toward REST frontier (≤1h, CMS gap_fill_min_sec) ─
		// No live_ok / thin-span gate: multi-hour holes must walk back even when history is long.
		$gap = $this->_poll_gap_fill_telemetry($api_key, $device_id, $cache, $cache_path, $now, $rest_frontier);
		if (!empty($gap['rate_limited'])){
			$cache['graphql_backoff_until'] = $now + self::USAGE_RATE_BACKOFF_SEC;
			$cache['graphql_last_error'] = !empty($gap['error']) ? $gap['error'] : 'Too many requests';
		}
		// skip_reason already stored on $cache by gap-fill helper

		$cache['last_fetch'] = $now;
		$this->_recompute_half_hours_from_samples($cache);
		$this->_prune_mini_cache($cache, $now);
		$this->_save_mini_cache($cache_path, $cache);

	}

	/**
	 * Step 1 main: [last_sample − overlap, now], never more than 1h, never before REST frontier.
	 * Cold start (no samples): [now − 1h, now].
	 *
	 * @return array{ok:int,rate_limited:int,auth_failed:int,error:string,n:int}
	 */
	function _poll_recent_telemetry($api_key, $device_id, &$cache, $now, $rest_frontier){

		$now = (int)$now;
		$cap = $now - self::USAGE_MAIN_MAX_SEC;
		$floor = max((int)$rest_frontier, $cap);

		$last_sample = $this->_cache_last_sample_ts($cache);
		if ($last_sample > 0){
			// From last cached sample (small overlap) → now; cap so span ≤ 1h
			$start = $last_sample - self::USAGE_OVERLAP_SEC;
			if ($start < $floor){
				$start = $floor;
			}
		} else {
			// Cold start: full hour once
			$start = $floor;
		}

		if ($start >= $now - 10){
			// Still pull a short tail so live chip updates when already nearly current
			$start = max($floor, $now - self::USAGE_OVERLAP_SEC);
		}
		if ($start >= $now){
			return ['ok' => 1, 'rate_limited' => 0, 'auth_failed' => 0, 'error' => '', 'n' => 0];
		}

		$result = $this->_graphql_telemetry_with_reauth($api_key, $device_id, $start, $now);
		if (!empty($result['rate_limited'])){
			return [
				'ok' => 0,
				'rate_limited' => 1,
				'auth_failed' => 0,
				'error' => !empty($result['error']) ? $result['error'] : 'Too many requests',
				'n' => 0,
			];
		}
		if (!empty($result['auth_failed'])){
			return [
				'ok' => 0,
				'rate_limited' => 0,
				'auth_failed' => 1,
				'error' => !empty($result['error']) ? $result['error'] : 'Auth failed',
				'n' => 0,
			];
		}
		if (!empty($result['rows'])){
			$this->_merge_telemetry_rows($cache, $result['rows']);
		}
		return [
			'ok' => !empty($result['ok']) ? 1 : 0,
			'rate_limited' => 0,
			'auth_failed' => 0,
			'error' => !empty($result['error']) ? $result['error'] : '',
			'n' => !empty($result['rows']) ? count($result['rows']) : 0,
		];

	}

	/**
	 * Step 2 backfill: one backward ≤1h window from the start of continuous Mini coverage.
	 *
	 * last_gap_fill is claimed atomically on disk (LOCK_EX read/compare/write) before
	 * any gap-fill network, so concurrent refresh_energy cannot double-fire the same window.
	 *
	 * @return array{ok:int,rate_limited:int,error:string,n:int,skipped:int,skip_reason:string}
	 */
	function _poll_gap_fill_telemetry($api_key, $device_id, &$cache, $cache_path, $now, $rest_frontier){

		$skip = function($reason) use (&$cache){
			$cache['gap_fill_last_skip'] = $reason;
			return [
				'ok' => 1,
				'rate_limited' => 0,
				'error' => '',
				'n' => 0,
				'skipped' => 1,
				'skip_reason' => $reason,
			];
		};

		$gap_min = $this->get_gap_fill_min_sec();

		// Cheap in-memory pre-check (authoritative check is atomic claim below)
		$last_gap = !empty($cache['last_gap_fill']) ? (int)$cache['last_gap_fill'] : 0;
		if ($last_gap > 0 && ($now - $last_gap) < $gap_min){
			return $skip('min_interval');
		}

		$gap_floor = (int)$rest_frontier;
		$gap_end = $this->_cache_continuous_mini_start($cache, $now);
		if ($gap_end <= 0){
			$gap_end = $this->_cache_last_sample_ts($cache);
		}
		if ($gap_end <= 0){
			return $skip('no_samples');
		}
		if ($gap_end <= $gap_floor + 120){
			return $skip('no_hole');
		}

		$token = $this->_graphql_token($api_key);
		if ($token === ''){
			return $skip('no_token');
		}

		// Points budget: only skip when API clearly says blocked or nearly empty.
		// Fail-open if rateLimitInfo missing (old fail-closed isBlocked=true blocked all gap-fill).
		$info = $this->_graphql_rate_limit_info($token);
		$known = !empty($info['known']);
		$blocked = !empty($info['isBlocked']);
		$remaining = (int)($info['remainingPoints'] ?? 0);
		// Soft floor when known; unknown → allow attempt (telemetry will 1199 if truly blocked)
		$min_points = 1000;
		if ($known && ($blocked || $remaining <= $min_points)){
			return $skip($blocked ? 'points_blocked' : 'points_low');
		}

		$fill_end = $gap_end + self::USAGE_OVERLAP_SEC;
		if ($fill_end > $now){
			$fill_end = $now;
		}
		$fill_start = $fill_end - self::USAGE_GAP_FILL_MAX_SEC;
		if ($fill_start < $gap_floor){
			$fill_start = $gap_floor;
		}
		if ($fill_end - $fill_start < 60){
			return $skip('window_short');
		}

		// Atomic claim: lock file → re-read last_gap_fill → write now → unlock → then poll
		if (!$this->_claim_gap_fill_slot($cache_path, $device_id, $now, $gap_min, $cache)){
			return $skip('min_interval');
		}

		$cache['gap_fill_last_skip'] = 'attempt';
		$cache['gap_fill_last_window'] = [
			'start' => $fill_start,
			'end' => $fill_end,
			'floor' => $gap_floor,
			'gap_end' => $gap_end,
		];
		$result = $this->_graphql_telemetry_with_reauth($api_key, $device_id, $fill_start, $fill_end);
		if (!empty($result['rate_limited'])){
			$cache['gap_fill_last_skip'] = 'rate_limited';
			return [
				'ok' => 0,
				'rate_limited' => 1,
				'error' => !empty($result['error']) ? $result['error'] : 'Too many requests',
				'n' => 0,
				'skipped' => 0,
				'skip_reason' => 'rate_limited',
			];
		}
		if (!empty($result['auth_failed'])){
			$cache['gap_fill_last_skip'] = 'auth_failed';
			return [
				'ok' => 0,
				'rate_limited' => 0,
				'error' => !empty($result['error']) ? $result['error'] : 'Auth failed',
				'n' => 0,
				'skipped' => 0,
				'skip_reason' => 'auth_failed',
			];
		}
		if (!empty($result['rows'])){
			$this->_merge_telemetry_rows($cache, $result['rows']);
		}
		$n = !empty($result['rows']) ? count($result['rows']) : 0;
		$cache['gap_fill_last_skip'] = $n > 0 ? 'ok' : 'ok_empty';
		return [
			'ok' => !empty($result['ok']) ? 1 : 0,
			'rate_limited' => 0,
			'error' => !empty($result['error']) ? $result['error'] : '',
			'n' => $n,
			'skipped' => 0,
			'skip_reason' => $n > 0 ? 'ok' : 'ok_empty',
		];

	}

	/**
	 * Start of continuous Mini coverage ending at the newest Mini half-hour / sample.
	 * Walks half-hour steps backward while slots exist (mini samples or mini half_hours).
	 * This is the edge gap-fill extends from — not the absolute oldest orphaned half-hour.
	 */
	function _cache_continuous_mini_start($cache, $now){

		$slots = [];

		if (!empty($cache['half_hours']) && is_array($cache['half_hours'])){
			foreach ($cache['half_hours'] as $k => $row){
				$src = is_array($row) ? ($row['source'] ?? 'mini') : 'mini';
				if ($src === 'rest'){
					continue;
				}
				$slots[(int)$k] = 1;
			}
		}

		// Also treat sample presence as covering that half-hour (current open slot)
		if (!empty($cache['samples']) && is_array($cache['samples'])){
			foreach (array_keys($cache['samples']) as $k){
				$t = (int)$k;
				$slot = $t - ($t % 1800);
				$slots[$slot] = 1;
			}
		}

		if (empty($slots)){
			return 0;
		}

		// Newest mini slot
		$latest = 0;
		foreach (array_keys($slots) as $t){
			if ($t > $latest){
				$latest = $t;
			}
		}

		// Walk back while previous half-hour exists
		$t = $latest;
		while (isset($slots[$t - 1800])){
			$t -= 1800;
		}

		return $t;

	}

	function _cache_last_sample_ts($cache){

		$last = 0;
		if (!empty($cache['samples']) && is_array($cache['samples'])){
			foreach (array_keys($cache['samples']) as $k){
				$t = (int)$k;
				if ($t > $last){
					$last = $t;
				}
			}
		}
		return $last;

	}

	function _cache_oldest_sample_ts($cache){

		$oldest = 0;
		if (!empty($cache['samples']) && is_array($cache['samples'])){
			foreach (array_keys($cache['samples']) as $k){
				$t = (int)$k;
				if ($oldest === 0 || $t < $oldest){
					$oldest = $t;
				}
			}
		}
		return $oldest;

	}

	function _merge_telemetry_rows(&$cache, $rows){

		if (empty($cache['samples']) || !is_array($cache['samples'])){
			$cache['samples'] = [];
		}
		foreach ($rows as $row){
			$ts = (int)$row['ts'];
			$cache['samples'][(string)$ts] = (float)$row['demand_w'];
		}

	}

	/**
	 * @return array{limit:int,remainingPoints:int,usedPoints:int,ttl:int,isBlocked:bool,known:int}
	 */
	function _graphql_rate_limit_info($token){

		$q = '{ rateLimitInfo { pointsAllowanceRateLimit { limit remainingPoints usedPoints ttl isBlocked } } }';
		$res = $this->_graphql_post($q, $token);
		$row = $res['data']['rateLimitInfo']['pointsAllowanceRateLimit'] ?? null;
		if (!is_array($row)){
			// Fail-open: unknown budget must not block gap-fill forever
			return [
				'limit' => 0,
				'remainingPoints' => 0,
				'usedPoints' => 0,
				'ttl' => 0,
				'isBlocked' => false,
				'known' => 0,
			];
		}
		return [
			'limit' => (int)($row['limit'] ?? 0),
			'remainingPoints' => (int)($row['remainingPoints'] ?? 0),
			'usedPoints' => (int)($row['usedPoints'] ?? 0),
			'ttl' => (int)($row['ttl'] ?? 0),
			'isBlocked' => !empty($row['isBlocked']),
			'known' => 1,
		];

	}

	function _recompute_half_hours_from_samples(&$cache){

		if (empty($cache['samples']) || !is_array($cache['samples'])){
			return;
		}

		// Bucket samples by half-hour; do not overwrite REST-final buckets
		// Store mean demand power (kW); partial-slot energy scaling is applied when building series
		$buckets = [];
		foreach ($cache['samples'] as $ts_s => $w){
			$ts = (int)$ts_s;
			$slot = $ts - ($ts % 1800);
			if (!isset($buckets[$slot])){
				$buckets[$slot] = ['sum' => 0.0, 'n' => 0];
			}
			$buckets[$slot]['sum'] += (float)$w;
			$buckets[$slot]['n']++;
		}

		if (empty($cache['half_hours']) || !is_array($cache['half_hours'])){
			$cache['half_hours'] = [];
		}

		foreach ($buckets as $slot => $agg){
			$key = (string)(int)$slot;
			if (!empty($cache['half_hours'][$key]['source']) && $cache['half_hours'][$key]['source'] === 'rest'){
				continue;
			}
			if ($agg['n'] < 1){
				continue;
			}
			$avg_w = $agg['sum'] / $agg['n'];
			$cache['half_hours'][$key] = [
				// Mean demand power — NOT energy-scaled (series builder scales open slots)
				'kw' => round($avg_w / 1000.0, 4),
				'source' => 'mini',
				'n' => (int)$agg['n'],
			];
		}

	}

	/**
	 * Half-hour series for the graph.
	 * Complete slots: average kW (Mini mean demand, or REST kWh×2).
	 * Open Mini slot (prediction fill): full 30 min width —
	 *   display = curr×(elapsed/1800) + prev×(remaining/1800)
	 * so the new half-hour starts near the previous level and slides toward truth.
	 */
	function _usage_series_from_cache_half_hours($cache, $win_start, $win_end){

		$series = [];
		if (empty($cache['half_hours']) || !is_array($cache['half_hours'])){
			return $series;
		}

		$now = time();
		$end = min($win_end, $now + 1800);
		$hh = $cache['half_hours'];

		for ($ts = $win_start; $ts < $end; $ts += 1800){
			$key = (string)$ts;
			if (empty($hh[$key])){
				continue;
			}
			$row = $hh[$key];
			$mean_kw = is_array($row) ? (float)$row['kw'] : (float)$row;
			$source = is_array($row) && !empty($row['source']) ? $row['source'] : 'mini';
			$slot_end = $ts + 1800;

			$kw = $mean_kw;
			$ts_to = $slot_end;
			$partial = 0;

			// Open Mini half-hour: blend current mean with previous slot for missing minutes
			if ($source !== 'rest' && $now < $slot_end){
				$elapsed = $now - $ts;
				if ($elapsed < 1){
					$elapsed = 1;
				}
				if ($elapsed > 1800){
					$elapsed = 1800;
				}
				$remaining = 1800 - $elapsed;

				$curr = $mean_kw;
				$prev_key = (string)($ts - 1800);
				$prev = null;
				if (!empty($hh[$prev_key])){
					$prev_row = $hh[$prev_key];
					$prev = is_array($prev_row) ? (float)$prev_row['kw'] : (float)$prev_row;
				}
				// No previous half-hour: treat prev as curr (rare)
				if ($prev === null){
					$prev = $curr;
				}
				// No samples yet in current → mean may be missing; fall back to prev
				if ($curr <= 0 && $prev > 0 && empty($row['n'])){
					$curr = $prev;
				}

				$kw = round(
						$curr * ($elapsed / 1800.0) + $prev * ($remaining / 1800.0),
						4
				);
				$ts_to = $slot_end;
				$partial = 1;
			}

			$series[] = [
				'ts_from' => $ts,
				'ts_to' => $ts_to,
				'kw' => $kw,
				'source' => $source,
				'partial' => $partial,
			];
		}

		return $series;

	}

	function _usage_series_from_half_hours($map, $win_start, $win_end){

		$series = [];
		$now = time();
		$end = min($win_end, $now + 1800);
		for ($ts = $win_start; $ts < $end; $ts += 1800){
			if (!isset($map[$ts])){
				continue;
			}
			$series[] = [
				'ts_from' => $ts,
				'ts_to' => $ts + 1800,
				'kw' => (float)$map[$ts],
				'source' => 'rest',
			];
		}
		return $series;

	}

	/**
	 * REST half-hours as billing truth: kW = kWh × 2.
	 * Steady state: network every USAGE_REST_MIN_SEC; ask from last REST tip end forward
	 * at most USAGE_REST_MAX_FETCH_SEC (6 h) so holes fill in chunks (not full 48h each time).
	 * Cold start: from $period_from, max 6 h per call. Merges into file map.
	 *
	 * @param bool $allow_network when false, only return file cache (even if stale)
	 * @param bool $force_refresh bypass USAGE_REST_MIN_SEC when networking
	 * @return array<int,float> ts_from => kw
	 */
	function _rest_half_hours_map($api_key, $mpan, $meter_serial, $period_from, $period_to, $allow_network = true, $force_refresh = false){

		if ($api_key === '' || $mpan === '' || $meter_serial === ''){
			return [];
		}

		$path = $GLOBALS['config']['base_path'].'cache/energy_usage_rest_'.
				preg_replace('/\D/', '', $mpan).'_'.preg_replace('/[^A-Za-z0-9\-]/', '_', $meter_serial).'.json';

		$map = [];
		$fetched_at = 0;
		if (is_file($path)){
			$raw = @file_get_contents($path);
			if ($raw !== false && $raw !== ''){
				$data = cms_json_decode($raw, $path);
				if (is_array($data) && !empty($data['map']) && is_array($data['map'])){
					foreach ($data['map'] as $k => $v){
						$map[(int)$k] = (float)$v;
					}
					$fetched_at = !empty($data['fetched_at']) ? (int)$data['fetched_at'] : 0;
				}
			}
		}

		$age = $fetched_at > 0 ? (time() - $fetched_at) : 999999;
		// Fresh cache (unless force), or display path: return accumulated map without network
		if (!empty($map) && !$force_refresh && ($age < self::USAGE_REST_MIN_SEC || !$allow_network)){
			return $map;
		}

		if (!$allow_network){
			return $map;
		}

		// Network window: from last REST end (or seed) forward, max 6 h per call
		$fetch_from = (int)$period_from;
		$cap_to = (int)$period_to;
		if ($cap_to <= 0){
			$cap_to = time();
		}
		if (!empty($map)){
			$last_ts = 0;
			foreach (array_keys($map) as $ts){
				$t = (int)$ts;
				if ($t > $last_ts){
					$last_ts = $t;
				}
			}
			// Next half-hour after last REST slot (1-slot overlap: re-ask last for late corrections)
			$fetch_from = $last_ts > 0 ? ($last_ts - 1800) : (int)$period_from;
			if ($fetch_from < (int)$period_from){
				$fetch_from = (int)$period_from;
			}
		}
		// Cap ask length at 6 h so we walk REST→now in chunks
		$fetch_to = $fetch_from + self::USAGE_REST_MAX_FETCH_SEC;
		if ($fetch_to > $cap_to){
			$fetch_to = $cap_to;
		}
		if ($fetch_from >= $fetch_to){
			return $map;
		}

		$fetched = $this->_fetch_consumption($api_key, $mpan, $meter_serial, $fetch_from, $fetch_to);
		if (empty($fetched['ok']) || empty($fetched['by_from'])){
			// Keep prior map on failure
			return $map;
		}

		foreach ($fetched['by_from'] as $ts => $kwh){
			$map[(int)$ts] = round(((float)$kwh) * 2, 4);
		}

		// Drop half-hours older than keep window (same idea as mini half_hours)
		$cut = time() - self::USAGE_HALF_KEEP_SEC;
		foreach (array_keys($map) as $ts){
			if ((int)$ts < $cut){
				unset($map[$ts]);
			}
		}

		@file_put_contents($path, json_encode([
			'fetched_at' => time(),
			'map' => $map,
		], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

		return $map;

	}

	/**
	 * Kraken JWT for GraphQL Mini (not used by REST prices/consumption).
	 *
	 * L1: per-request static. L2: disk cache/energy_graphql_token.json (GRAPHQL_TOKEN_TTL_SEC).
	 * obtainKrakenToken only when both miss and auth backoff is clear.
	 *
	 * @param bool $force_new skip token cache (one reauth after AUTHORIZATION); still honours auth backoff
	 */
	function _graphql_token($api_key, $force_new = false){

		$api_key = trim((string)$api_key);
		if ($api_key === ''){
			return '';
		}

		// Per-request static
		static $mem = [];
		$key_hash = $this->_graphql_key_hash($api_key);
		$now = time();

		if ($force_new){
			unset($mem[$key_hash]);
		}

		if (!$force_new && !empty($mem[$key_hash]['token']) && !empty($mem[$key_hash]['exp']) && (int)$mem[$key_hash]['exp'] > $now){
			return (string)$mem[$key_hash]['token'];
		}

		if (!$force_new){
			$disk = $this->_graphql_token_disk_read($key_hash);
			if (!empty($disk['token']) && !empty($disk['exp']) && (int)$disk['exp'] > $now){
				$mem[$key_hash] = ['token' => (string)$disk['token'], 'exp' => (int)$disk['exp']];
				return (string)$disk['token'];
			}
		}

		if ($this->_graphql_auth_backoff_active()){
			return '';
		}

		// Escape API key for GraphQL string
		$key_js = str_replace(['\\', '"'], ['\\\\', '\\"'], $api_key);
		$query = 'mutation { obtainKrakenToken(input: {APIKey: "'.$key_js.'"}) { token } }';
		$res = $this->_graphql_post($query, null);

		$token = '';
		if (!empty($res['data']['obtainKrakenToken']['token'])){
			$token = (string)$res['data']['obtainKrakenToken']['token'];
		}

		if ($token !== ''){
			$exp = $now + self::GRAPHQL_TOKEN_TTL_SEC;
			$mem[$key_hash] = ['token' => $token, 'exp' => $exp];
			$this->_graphql_token_disk_write($key_hash, $token, $exp, 0, '');
			return $token;
		}

		// Login failed — parse rate limit / message; always cool down so menu polls do not spam
		$err = $this->_graphql_errors_summary($res);
		$msg = $err['message'] !== '' ? $err['message'] : 'obtainKrakenToken failed';
		if (!empty($err['rate_limited'])){
			$msg = $err['message'] !== '' ? $err['message'] : 'Too many requests (login)';
		}
		$this->_graphql_auth_backoff_set(self::USAGE_AUTH_BACKOFF_SEC, $msg);
		unset($mem[$key_hash]);
		$this->_graphql_token_invalidate_disk($key_hash);
		return '';

	}

	function _graphql_key_hash($api_key){

		return hash('sha256', (string)$api_key);

	}

	function _graphql_token_path(){

		return $GLOBALS['config']['base_path'].'cache/energy_graphql_token.json';

	}

	/**
	 * @return array{token?:string,exp?:int,auth_backoff_until?:int,auth_last_error?:string,key_hash?:string}
	 */
	function _graphql_token_disk_load(){

		$path = $this->_graphql_token_path();
		if (!is_file($path)){
			return [];
		}
		$raw = @file_get_contents($path);
		if ($raw === false || $raw === ''){
			return [];
		}
		$data = cms_json_decode($raw, $path);
		return is_array($data) ? $data : [];

	}

	/**
	 * @return array{token:string,exp:int}|array{}
	 */
	function _graphql_token_disk_read($key_hash){

		$data = $this->_graphql_token_disk_load();
		if (empty($data['key_hash']) || (string)$data['key_hash'] !== (string)$key_hash){
			return [];
		}
		if (empty($data['token']) || empty($data['exp'])){
			return [];
		}
		return [
			'token' => (string)$data['token'],
			'exp' => (int)$data['exp'],
		];

	}

	function _graphql_token_disk_write($key_hash, $token, $exp, $auth_backoff_until = null, $auth_last_error = null){

		$path = $this->_graphql_token_path();
		$prev = $this->_graphql_token_disk_load();
		$data = [
			'key_hash' => (string)$key_hash,
			'token' => (string)$token,
			'exp' => (int)$exp,
			'auth_backoff_until' => $auth_backoff_until !== null
					? (int)$auth_backoff_until
					: (int)($prev['auth_backoff_until'] ?? 0),
			'auth_last_error' => $auth_last_error !== null
					? (string)$auth_last_error
					: (string)($prev['auth_last_error'] ?? ''),
			'updated_at' => time(),
		];
		$this->_graphql_token_disk_save($data);

	}

	function _graphql_token_invalidate_disk($key_hash = ''){

		$data = $this->_graphql_token_disk_load();
		if ($key_hash !== '' && !empty($data['key_hash']) && (string)$data['key_hash'] !== (string)$key_hash){
			// Different key's file — leave backoff, clear only if same file was ours
			return;
		}
		$data['token'] = '';
		$data['exp'] = 0;
		if ($key_hash !== ''){
			$data['key_hash'] = (string)$key_hash;
		}
		$data['updated_at'] = time();
		$this->_graphql_token_disk_save($data);

	}

	function _graphql_token_disk_save(array $data){

		$path = $this->_graphql_token_path();
		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		if ($json === false){
			return;
		}
		$fp = @fopen($path, 'c+');
		if ($fp === false){
			@file_put_contents($path, $json);
			return;
		}
		if (@flock($fp, LOCK_EX)){
			ftruncate($fp, 0);
			rewind($fp);
			fwrite($fp, $json);
			fflush($fp);
			flock($fp, LOCK_UN);
		}
		fclose($fp);

	}

	function _graphql_auth_backoff_active(){

		$data = $this->_graphql_token_disk_load();
		$until = !empty($data['auth_backoff_until']) ? (int)$data['auth_backoff_until'] : 0;
		return $until > time();

	}

	function _graphql_auth_backoff_set($seconds, $message = ''){

		$seconds = max(1, (int)$seconds);
		$data = $this->_graphql_token_disk_load();
		$data['auth_backoff_until'] = time() + $seconds;
		$data['auth_last_error'] = (string)$message;
		$data['token'] = '';
		$data['exp'] = 0;
		if (empty($data['key_hash'])){
			$data['key_hash'] = '';
		}
		$data['updated_at'] = time();
		$this->_graphql_token_disk_save($data);

	}

	/**
	 * @return array{message:string,rate_limited:int,auth_error:int}
	 */
	function _graphql_errors_summary($res){

		$out = ['message' => '', 'rate_limited' => 0, 'auth_error' => 0];
		if (empty($res['errors']) || !is_array($res['errors'])){
			return $out;
		}
		foreach ($res['errors'] as $err){
			$m = is_array($err) ? (string)($err['message'] ?? '') : (string)$err;
			$code = is_array($err) ? (string)($err['extensions']['errorCode'] ?? '') : '';
			$type = is_array($err) ? (string)($err['extensions']['errorType'] ?? '') : '';
			if ($out['message'] === '' && $m !== ''){
				$out['message'] = $m;
			}
			if ($code === 'KT-CT-1199' || stripos($m, 'too many requests') !== false){
				$out['rate_limited'] = 1;
				if ($m !== ''){
					$out['message'] = $m;
				}
				// Do not also mark as auth_error — 1199 is request/login pressure, not bad JWT
				continue;
			}
			if ($type === 'AUTHORIZATION'
					|| $code === 'KT-CT-1112'
					|| $code === 'KT-CT-1121'
					|| stripos($m, 'authorization') !== false
					|| stripos($m, 'jwt') !== false
					|| stripos($m, 'unauthoriz') !== false){
				$out['auth_error'] = 1;
			}
		}
		return $out;

	}

	/**
	 * Telemetry with at most one reauth (invalidate + force obtainKrakenToken), then 5 min auth backoff.
	 *
	 * @return array{ok:int,rows:list,error:string,rate_limited:int,auth_error:int,auth_failed:int}
	 */
	function _graphql_telemetry_with_reauth($api_key, $device_id, $period_from, $period_to){

		$token = $this->_graphql_token($api_key);
		if ($token === ''){
			return [
				'ok' => 0,
				'rows' => [],
				'error' => 'No GraphQL token',
				'rate_limited' => 0,
				'auth_error' => 0,
				'auth_failed' => 1,
			];
		}

		$result = $this->_graphql_telemetry($token, $device_id, $period_from, $period_to);
		if (empty($result['auth_error'])){
			$result['auth_failed'] = 0;
			return $result;
		}

		// Max one reauth: drop cached JWT, obtainKrakenToken once, retry telemetry once
		$key_hash = $this->_graphql_key_hash($api_key);
		$this->_graphql_token_invalidate($key_hash);
		$token2 = $this->_graphql_token($api_key, true);
		if ($token2 === ''){
			// obtain failed → _graphql_token already set auth backoff
			return [
				'ok' => 0,
				'rows' => [],
				'error' => !empty($result['error']) ? $result['error'] : 'Auth failed (reauth no token)',
				'rate_limited' => 0,
				'auth_error' => 1,
				'auth_failed' => 1,
			];
		}

		$result2 = $this->_graphql_telemetry($token2, $device_id, $period_from, $period_to);
		if (!empty($result2['auth_error'])){
			$msg = !empty($result2['error']) ? $result2['error'] : 'Auth failed after reauth';
			$this->_graphql_auth_backoff_set(self::USAGE_AUTH_BACKOFF_SEC, $msg);
			$result2['auth_failed'] = 1;
			return $result2;
		}

		$result2['auth_failed'] = 0;
		return $result2;

	}

	/**
	 * Drop disk + request-static token for this API key hash (before a single reauth).
	 */
	function _graphql_token_invalidate($key_hash){

		$this->_graphql_token_invalidate_disk($key_hash);
		// Static cache lives inside _graphql_token; force_new=true skips it on next obtain.
		// Mark disk empty so concurrent requests do not reuse a dead JWT.
	}

	function _resolve_device_id($api_key, $account_number, $mpan, $meter_serial, $device_id, $allow_network = true){

		if ($device_id !== ''){
			return $device_id;
		}

		// Cached discovery
		$path = $GLOBALS['config']['base_path'].'cache/energy_graphql_device.json';
		if (is_file($path)){
			$raw = @file_get_contents($path);
			if ($raw !== false && $raw !== ''){
				$data = cms_json_decode($raw, $path);
				if (is_array($data) && !empty($data['device_id'])
						&& (empty($mpan) || empty($data['mpan']) || $data['mpan'] === $mpan)
						&& !empty($data['fetched_at'])
						&& ((time() - (int)$data['fetched_at']) < 86400 || !$allow_network)){
					return (string)$data['device_id'];
				}
			}
		}

		if (!$allow_network){
			return '';
		}

		if ($account_number === '' && $mpan === ''){
			return '';
		}

		$token = $this->_graphql_token($api_key);
		if ($token === ''){
			return '';
		}

		// Need account number
		if ($account_number === ''){
			$viewer = $this->_graphql_post('{ viewer { accounts { number } } }', $token);
			if (!empty($viewer['data']['viewer']['accounts'][0]['number'])){
				$account_number = (string)$viewer['data']['viewer']['accounts'][0]['number'];
			}
		}
		if ($account_number === ''){
			return '';
		}

		$acc_js = str_replace(['\\', '"'], ['\\\\', '\\"'], $account_number);
		$q = 'query { account(accountNumber: "'.$acc_js.'") { electricityAgreements(active: true) { '
				.'meterPoint { mpan meters(includeInactive: false) { serialNumber smartDevices { deviceId } } } } } }';
		$res = $this->_graphql_post($q, $token);
		$found = '';
		$found_mpan = '';

		if (!empty($res['data']['account']['electricityAgreements']) && is_array($res['data']['account']['electricityAgreements'])){
			foreach ($res['data']['account']['electricityAgreements'] as $ag){
				$mp = $ag['meterPoint'] ?? null;
				if (!is_array($mp)){
					continue;
				}
				$mpan_here = preg_replace('/\D/', '', (string)($mp['mpan'] ?? ''));
				if ($mpan !== '' && $mpan_here !== $mpan){
					continue;
				}
				if (empty($mp['meters']) || !is_array($mp['meters'])){
					continue;
				}
				foreach ($mp['meters'] as $meter){
					$ser = strtoupper(trim((string)($meter['serialNumber'] ?? '')));
					if ($meter_serial !== '' && $ser !== $meter_serial){
						continue;
					}
					if (!empty($meter['smartDevices'][0]['deviceId'])){
						$found = (string)$meter['smartDevices'][0]['deviceId'];
						$found_mpan = $mpan_here;
						break 2;
					}
				}
			}
		}

		if ($found !== ''){
			@file_put_contents($path, json_encode([
				'device_id' => $found,
				'mpan' => $found_mpan,
				'fetched_at' => time(),
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}

		return $found;

	}

	/**
	 * @return array{ok:int,rows:list,error:string,rate_limited:int,auth_error:int}
	 */
	function _graphql_telemetry($token, $device_id, $period_from, $period_to){

		$dev_js = str_replace(['\\', '"'], ['\\\\', '\\"'], $device_id);
		try {
			$tz = new \DateTimeZone('UTC');
			$start = (new \DateTime('@'.(int)$period_from))->setTimezone($tz)->format('Y-m-d\TH:i:s\Z');
			$end = (new \DateTime('@'.(int)$period_to))->setTimezone($tz)->format('Y-m-d\TH:i:s\Z');
		} catch (\Exception $e) {
			$start = gmdate('Y-m-d\TH:i:s\Z', (int)$period_from);
			$end = gmdate('Y-m-d\TH:i:s\Z', (int)$period_to);
		}

		$q = '{ smartMeterTelemetry(deviceId: "'.$dev_js.'", grouping: TEN_SECONDS, start: "'.$start.'", end: "'.$end.'") { readAt demand } }';
		// Pass window so apis.log includes asked range on usage graphql lines
		$res = $this->_graphql_post($q, $token, (int)$period_from, (int)$period_to);

		// GraphQL errors (e.g. KT-CT-1199 / AUTHORIZATION) — do not treat as empty success
		if (!empty($res['errors']) && is_array($res['errors'])){
			$err = $this->_graphql_errors_summary($res);
			return [
				'ok' => 0,
				'rows' => [],
				'error' => $err['message'] !== '' ? $err['message'] : 'GraphQL error',
				'rate_limited' => !empty($err['rate_limited']) ? 1 : 0,
				'auth_error' => !empty($err['auth_error']) ? 1 : 0,
			];
		}

		$rows = [];
		if (empty($res['data']['smartMeterTelemetry']) || !is_array($res['data']['smartMeterTelemetry'])){
			return ['ok' => 1, 'rows' => [], 'error' => '', 'rate_limited' => 0, 'auth_error' => 0];
		}

		foreach ($res['data']['smartMeterTelemetry'] as $row){
			if (empty($row['readAt']) || !array_key_exists('demand', $row) || $row['demand'] === null){
				continue;
			}
			$ts = strtotime($row['readAt']);
			if ($ts === false || $ts <= 0){
				continue;
			}
			$rows[] = [
				'ts' => (int)$ts,
				'demand_w' => (float)$row['demand'],
			];
		}

		return ['ok' => 1, 'rows' => $rows, 'error' => '', 'rate_limited' => 0, 'auth_error' => 0];

	}

	/**
	 * One-line log of a real outbound provider HTTP call → cache/apis.log
	 * Format: H:i:s <label> [- window] - result <token>
	 */
	function _log_api_call($message){

		$path = $GLOBALS['config']['base_path'].'cache/apis.log';
		$line = date('H:i:s').' '.$message."\n";
		@file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

	}

	/**
	 * Classify GraphQL HTTP/body into a short result token for apis.log
	 *
	 * @param mixed $response raw body or false
	 * @param array|null $decoded
	 */
	function _graphql_result_word($response, $decoded){

		if ($response === false){
			return 'fail';
		}
		if (!is_array($decoded)){
			return 'empty';
		}
		if (!empty($decoded['errors']) && is_array($decoded['errors'])){
			$err = $this->_graphql_errors_summary($decoded);
			if (!empty($err['rate_limited'])){
				return '1199';
			}
			if (!empty($err['auth_error'])){
				return 'auth';
			}
			return 'error';
		}
		return 'ok';

	}

	function _graphql_log_label($query){

		// Uniform: <Module> <type> <function>
		$q = (string)$query;
		if (stripos($q, 'obtainKrakenToken') !== false){
			return 'Octopus graphql auth';
		}
		if (stripos($q, 'smartMeterTelemetry') !== false){
			return 'Octopus graphql usage';
		}
		if (stripos($q, 'rateLimitInfo') !== false){
			return 'Octopus graphql rateLimitInfo';
		}
		if (stripos($q, 'electricityAgreements') !== false || stripos($q, 'smartDevices') !== false){
			return 'Octopus graphql device';
		}
		if (stripos($q, 'viewer') !== false){
			return 'Octopus graphql viewer';
		}
		return 'Octopus graphql other';

	}

	/**
	 * Build apis.log message:
	 *   H:i:s <Module> <type> <function> [- extra…] - result <token>
	 * Extra = optional window "H:i:s - H:i:s" and/or rate used "0.4%".
	 *
	 * @param string $extra free-form segment(s) already lower-case (no leading " - ")
	 */
	function _api_log_line($label, $result_word, $window_from = 0, $window_to = 0, $extra = ''){

		$msg = $label;
		if ((int)$window_from > 0 && (int)$window_to > 0){
			$msg .= ' - '.date('H:i:s', (int)$window_from).' - '.date('H:i:s', (int)$window_to);
		}
		$extra = trim((string)$extra);
		if ($extra !== ''){
			$msg .= ' - '.$extra;
		}
		$msg .= ' - result '.$result_word;
		$this->_log_api_call($msg);

	}

	/**
	 * @param int $window_from optional unix — asked start (usage graphql / rest usage)
	 * @param int $window_to optional unix — asked end
	 */
	function _graphql_post($query, $token, $window_from = 0, $window_to = 0){

		$headers = "Content-Type: application/json\r\nAccept: application/json\r\n";
		if ($token !== null && $token !== ''){
			$headers .= 'Authorization: JWT '.$token."\r\n";
		}

		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => $headers,
				'content' => json_encode(['query' => $query], JSON_UNESCAPED_UNICODE),
				'timeout' => 45,
				'ignore_errors' => true,
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			],
		]);

		$response = @file_get_contents('https://api.octopus.energy/v1/graphql/', false, $context);
		$decoded = null;
		if ($response !== false){
			$response = function_exists('cms_utf8_string') ? cms_utf8_string((string)$response) : (string)$response;
			$tmp = json_decode($response, true);
			$decoded = is_array($tmp) ? $tmp : null;
		}

		$label = $this->_graphql_log_label($query);
		$word = $this->_graphql_result_word($response, $decoded);
		$extra = '';
		// rateLimitInfo: log points used as percent of allowance (e.g. 0.4%)
		if ($label === 'Octopus graphql rateLimitInfo' && is_array($decoded)){
			$row = $decoded['data']['rateLimitInfo']['pointsAllowanceRateLimit'] ?? null;
			if (is_array($row)){
				$limit = (int)($row['limit'] ?? 0);
				$used = (int)($row['usedPoints'] ?? 0);
				if ($limit > 0){
					$pct = 100.0 * $used / $limit;
					$extra = number_format($pct, 1, '.', '').'%';
				}
			}
		}
		// Window only for usage telemetry
		if ($label === 'Octopus graphql usage'){
			$this->_api_log_line($label, $word, $window_from, $window_to, $extra);
		} else {
			$this->_api_log_line($label, $word, 0, 0, $extra);
		}

		return is_array($decoded) ? $decoded : [];

	}

	function _fetch_consumption($api_key, $mpan, $meter_serial, $period_from, $period_to){

		$from_iso = gmdate('Y-m-d\TH:i:s\Z', (int)$period_from);
		$to_iso = gmdate('Y-m-d\TH:i:s\Z', (int)$period_to);

		$url = 'https://api.octopus.energy/v1/electricity-meter-points/'.rawurlencode($mpan).
				'/meters/'.rawurlencode($meter_serial).
				'/consumption/?period_from='.rawurlencode($from_iso).
				'&period_to='.rawurlencode($to_iso).
				'&page_size=250&order_by=period';

		$by_from = [];
		$next = $url;
		$pages = 0;

		while ($next !== '' && $pages < 12){

			$pages++;
			$page = $this->_http_get_json($next, $api_key, (int)$period_from, (int)$period_to);
			if (empty($page['ok'])){
				return ['ok' => 0, 'error' => $page['error'], 'by_from' => []];
			}

			$body = $page['data'];
			if (!empty($body['results']) && is_array($body['results'])){
				foreach ($body['results'] as $row){
					if (!is_array($row) || empty($row['interval_start'])){
						continue;
					}
					$ts = strtotime($row['interval_start']);
					if ($ts === false || $ts <= 0){
						continue;
					}
					$ts = $ts - ($ts % 1800);
					if (!isset($row['consumption'])){
						continue;
					}
					$by_from[(int)$ts] = (float)$row['consumption'];
				}
			}

			$next = '';
			if (!empty($body['next']) && is_string($body['next'])){
				$next = $body['next'];
			}

		}

		if (empty($by_from)){
			return ['ok' => 0, 'error' => 'No consumption data', 'by_from' => []];
		}

		return ['ok' => 1, 'error' => '', 'by_from' => $by_from];

	}

	function _normalise_product_code($product_code){

		$product_code = strtoupper(trim((string)$product_code));
		$product_code = preg_replace('/[^A-Z0-9\-]/', '', $product_code);
		return $product_code;

	}

	function _normalise_region($region){

		$region = strtoupper(trim((string)$region));
		if (strlen($region) !== 1 || !preg_match('/^[A-Z]$/', $region)){
			return '';
		}
		return $region;

	}

	function _cache_path($product_code, $region){

		$safe_product = preg_replace('/[^A-Za-z0-9\-]/', '_', $product_code);
		$safe_region = preg_replace('/[^A-Za-z]/', '', $region);
		return $GLOBALS['config']['base_path'].'cache/energy_agile_'.$safe_region.'_'.$safe_product.'.json';

	}

	function _read_cache($path){

		if (!is_file($path)){
			return false;
		}

		$raw = @file_get_contents($path);
		if ($raw === false || $raw === ''){
			return false;
		}

		$data = cms_json_decode($raw, $path);
		if (!is_array($data) || empty($data['rates']) || !is_array($data['rates'])){
			return false;
		}

		return $data;

	}

	function _write_cache($path, $data){

		$payload = $data;
		unset($payload['from_cache'], $payload['new_prices'], $payload['series'], $payload['window_start'], $payload['window_end']);

		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		if ($json === false){
			return;
		}

		@file_put_contents($path, $json);

	}

	function _cache_needs_refresh($cached){

		$fetched_at = !empty($cached['fetched_at']) ? (int)$cached['fetched_at'] : 0;
		$max_valid_to = !empty($cached['max_valid_to']) ? (int)$cached['max_valid_to'] : 0;
		$now = time();

		if ($fetched_at <= 0){
			return true;
		}

		$age = $now - $fetched_at;
		$ttl = 1800;

		if ($this->_is_after_publish_window() && !$this->_horizon_covers_day_ahead($max_valid_to, $now)){
			$ttl = 300;
		}

		return $age >= $ttl;

	}

	function _is_after_publish_window(){

		try {
			$tz = new \DateTimeZone('Europe/London');
			$local = new \DateTime('now', $tz);
			$hour = (int)$local->format('G');
			return $hour >= 16;
		} catch (\Exception $e) {
			return ((int)gmdate('G')) >= 15;
		}

	}

	function _horizon_covers_day_ahead($max_valid_to, $now){

		if ($max_valid_to <= 0){
			return false;
		}

		try {
			$tz = new \DateTimeZone('Europe/London');
			$target = new \DateTime('now', $tz);
			$target->modify('+1 day');
			$target->setTime(21, 0, 0);
			return $max_valid_to >= $target->getTimestamp();
		} catch (\Exception $e) {
			return ($max_valid_to - $now) >= (28 * 3600);
		}

	}

	function _fetch_rates($product_code, $tariff_code, $period_from, $period_to){

		$from_iso = gmdate('Y-m-d\TH:i:s\Z', (int)$period_from);
		$to_iso = gmdate('Y-m-d\TH:i:s\Z', (int)$period_to);

		$url = 'https://api.octopus.energy/v1/products/'.rawurlencode($product_code).
				'/electricity-tariffs/'.rawurlencode($tariff_code).
				'/standard-unit-rates/?period_from='.rawurlencode($from_iso).
				'&period_to='.rawurlencode($to_iso).
				'&page_size=150';

		$all = [];
		$next = $url;
		$pages = 0;

		while ($next !== '' && $pages < 8){

			$pages++;
			$page = $this->_http_get_json($next, '', (int)$period_from, (int)$period_to);
			if (empty($page['ok'])){
				return ['ok' => 0, 'error' => $page['error'], 'rates' => [], 'max_valid_to' => 0];
			}

			$body = $page['data'];
			if (!empty($body['results']) && is_array($body['results'])){
				foreach ($body['results'] as $row){
					$normalised = $this->_normalise_rate_row($row);
					if ($normalised !== false){
						$all[] = $normalised;
					}
				}
			}

			$next = '';
			if (!empty($body['next']) && is_string($body['next'])){
				$next = $body['next'];
			}

		}

		if (empty($all)){
			return ['ok' => 0, 'error' => 'No unit rates returned', 'rates' => [], 'max_valid_to' => 0];
		}

		$by_from = [];
		$max_valid_to = 0;
		foreach ($all as $row){
			$by_from[$row['ts_from']] = $row;
			if ($row['ts_to'] > $max_valid_to){
				$max_valid_to = $row['ts_to'];
			}
		}

		$rates = array_values($by_from);
		usort($rates, function($a, $b){
			return $a['ts_from'] <=> $b['ts_from'];
		});

		return [
			'ok' => 1,
			'error' => '',
			'rates' => $rates,
			'max_valid_to' => $max_valid_to,
		];

	}

	function _normalise_rate_row($row){

		if (!is_array($row) || empty($row['valid_from'])){
			return false;
		}

		$ts_from = strtotime($row['valid_from']);
		$ts_to = !empty($row['valid_to']) ? strtotime($row['valid_to']) : 0;
		if ($ts_from === false || $ts_from <= 0){
			return false;
		}
		if ($ts_to === false || $ts_to <= 0){
			$ts_to = $ts_from + 1800;
		}

		$value = isset($row['value_inc_vat']) ? (float)$row['value_inc_vat'] : null;
		if ($value === null){
			return false;
		}

		return [
			'valid_from' => $row['valid_from'],
			'valid_to' => !empty($row['valid_to']) ? $row['valid_to'] : gmdate('Y-m-d\TH:i:s\Z', $ts_to),
			'value_inc_vat' => round($value, 4),
			'ts_from' => (int)$ts_from,
			'ts_to' => (int)$ts_to,
		];

	}

	/**
	 * @param string $url
	 * @param string $api_key Optional Octopus API key (Basic auth, empty password)
	 * @param int $window_from optional asked period start (unix) for apis.log
	 * @param int $window_to optional asked period end (unix)
	 */
	function _http_get_json($url, $api_key = '', $window_from = 0, $window_to = 0){

		$headers = "Accept: application/json\r\n";
		if ($api_key !== ''){
			$headers .= 'Authorization: Basic '.base64_encode($api_key.':')."\r\n";
		}

		$url_s = (string)$url;
		if (strpos($url_s, 'standard-unit-rates') !== false || strpos($url_s, 'unit-rates') !== false){
			$label = 'Octopus rest prices';
		} else if (strpos($url_s, 'consumption') !== false){
			$label = 'Octopus rest usage';
		} else {
			$label = 'Octopus rest other';
		}

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => $headers,
				'timeout' => 25,
				'ignore_errors' => true,
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			],
		]);

		$response = @file_get_contents($url, false, $context);

		$status = 0;
		if (!empty($http_response_header) && is_array($http_response_header)){
			foreach ($http_response_header as $hline){
				if (preg_match('#^HTTP/\S+\s+(\d+)#', $hline, $m)){
					$status = (int)$m[1];
					break;
				}
			}
		}

		if ($response === false){
			$this->_api_log_line($label, 'fail', $window_from, $window_to);
			return ['ok' => 0, 'error' => 'HTTP request failed'.($status ? ' (HTTP '.$status.')' : ''), 'data' => null];
		}

		if ($status >= 400){
			$this->_api_log_line($label, 'fail', $window_from, $window_to);
			return ['ok' => 0, 'error' => 'HTTP '.$status, 'data' => null];
		}

		$response = function_exists('cms_utf8_string') ? cms_utf8_string((string)$response) : (string)$response;
		$decoded = json_decode($response, true);
		if (!is_array($decoded)){
			$this->_api_log_line($label, 'empty', $window_from, $window_to);
			return ['ok' => 0, 'error' => 'Invalid JSON response', 'data' => null];
		}

		$this->_api_log_line($label, 'ok', $window_from, $window_to);
		return ['ok' => 1, 'error' => '', 'data' => $decoded];

	}

	function _empty_graph($product_code, $region, $error, $tariff_code = '', $window = null){

		if ($tariff_code === '' && $product_code !== '' && $region !== ''){
			$tariff_code = 'E-1R-'.$product_code.'-'.$region;
		}

		if ($window === null){
			$window = $this->_graph_window(time());
		}

		return [
			'ok' => 0,
			'series' => [],
			'window_start' => $window['start_ts'],
			'window_end' => $window['end_ts'],
			'product_code' => $product_code,
			'region' => $region,
			'tariff_code' => $tariff_code,
			'fetched_at' => 0,
			'max_valid_to' => 0,
			'from_cache' => 0,
			'new_prices' => 0,
			'error' => $error,
		];

	}

}
