<?php

namespace weather;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Forecast orchestrator: calls weather_forecast provider, owns weather_history, builds UI.
 * Providers: metoffice/forecast, openmeteo/forecast (CMS provides + run_action).
 */
class weather_model extends \Model {

	const DEFAULT_LAT = 51.88964722230162;
	const DEFAULT_LON = -2.1204895339274916;
	// 6 chips: free model data gets unreliable around day 7
	const DAY_CHIP_COUNT = 6;
	// BBC-style: weather day runs 06:00 → 05:59 next calendar day
	const DAY_START_HOUR = 6;

	// WMO weather codes treated as fog / mist family (show dots)
	const FOG_CODES = [45, 48];

	// Snow / ice family (show snow glyphs instead of rain drops)
	const SNOW_CODES = [71, 73, 75, 77, 85, 86];

	/**
	 * Menu chip HTML from weather_history / provider cache (no force refresh).
	 * e.g. 23° + sky icon span.
	 *
	 * @return string
	 */
	function get_menu_chip_html($latitude = null, $longitude = null){

		$lat = $this->_float_or($latitude, self::DEFAULT_LAT);
		$lon = $this->_float_or($longitude, self::DEFAULT_LON);

		// Prefer history (no network)
		$from_db = $this->_load_forecast_from_history($lat, $lon, 'Cheltenham');
		$hours = [];
		if (!empty($from_db['ok']) && !empty($from_db['hours_by_date'])){
			$hours = $from_db['hours_by_date'];
		} else {
			// Fall back to provider cache path (force_refresh false)
			$parsed = $this->get_forecast($lat, $lon, 'Cheltenham', false);
			$hours = !empty($parsed['hours_by_date']) ? $parsed['hours_by_date'] : [];
		}
		if ($hours instanceof \stdClass){
			$hours = (array)$hours;
		}
		if (empty($hours) || !is_array($hours)){
			return '';
		}

		$tz = new \DateTimeZone('Europe/London');
		$now = new \DateTime('now', $tz);
		$date_key = $this->weather_day_key($now);
		$hour = (int)$now->format('G');

		$slot = null;
		if (!empty($hours[$date_key]) && is_array($hours[$date_key])){
			foreach ($hours[$date_key] as $h){
				if (isset($h['hour']) && (int)$h['hour'] === $hour){
					$slot = $h;
					break;
				}
			}
			if ($slot === null && !empty($hours[$date_key])){
				$slot = $hours[$date_key][0];
			}
		}

		if ($slot === null || !isset($slot['temp_c']) || $slot['temp_c'] === null){
			return '';
		}

		$temp = (int)round((float)$slot['temp_c']);
		$sky = !empty($slot['sky']) ? preg_replace('/[^a-z0-9_]/', '', (string)$slot['sky']) : 'cloudy';
		return '<span class="menu_item_extra_text">'
				.htmlspecialchars((string)$temp, ENT_QUOTES, 'UTF-8')
				."\xC2\xB0"
				.'</span>'
				.'<span class="menu_item_sky" aria-hidden="true">'.$this->sky_html($sky).'</span>';

	}

	/**
	 * Weather panel settings (location + weather_provider).
	 */
	function get_weather_settings(){

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('weather/weather');
		return is_array($settings) ? $settings : [];

	}

	/**
	 * Panel name from weather settings or first weather panel instance
	 * (e.g. metoffice/forecast, openmeteo/forecast).
	 */
	function get_provider_panel(){

		$settings = $this->get_weather_settings();
		$panel = trim((string)($settings['weather_provider'] ?? ''));
		if ($panel !== ''){
			return $panel;
		}

		// Item fields live on page instances — scan weather/weather panels
		$this->load->model('cms/cms_page_panel_model');
		$panels = $this->cms_page_panel_model->get_cms_page_panels_by([
			'panel_name' => 'weather/weather',
		]);
		if (!empty($panels) && is_array($panels)){
			foreach ($panels as $p){
				if (!is_array($p)){
					continue;
				}
				$cand = trim((string)($p['weather_provider'] ?? ''));
				if ($cand !== ''){
					return $cand;
				}
			}
		}
		return '';

	}

	/**
	 * Call weather_forecast provider via CMS run_action.
	 *
	 * @return array
	 */
	function _call_forecast_provider($provider, $lat, $lon, $force_refresh = false){

		$provider = trim((string)$provider);
		if ($provider === ''){
			return ['ok' => 0, 'error' => 'Select weather forecast provider'];
		}

		$CI = null;
		if (function_exists('get_instance')){
			$CI = get_instance();
		}
		if ($CI === null || !is_object($CI) || !method_exists($CI, 'run_action')){
			return ['ok' => 0, 'error' => 'No controller for provider call'];
		}

		$result = $CI->run_action($provider, [
			'do' => 'forecast',
			'latitude' => $lat,
			'longitude' => $lon,
			'force_refresh' => $force_refresh ? 1 : 0,
			'return_result' => 1,
			'no_html' => 1,
		]);

		if (!is_array($result)){
			return ['ok' => 0, 'error' => 'Forecast provider failed'];
		}
		return $result;

	}

	/**
	 * Shared sky emoji for menu + main weather UI (no pseudo-elements).
	 * Drizzle uses cloudy base; stack overlay via sky_html / sky_overlay.
	 */
	function sky_glyph($sky){

		$sky = preg_replace('/[^a-z0-9_]/', '', (string)$sky);
		$map = [
			'clear' => "\xE2\x98\x80\xEF\xB8\x8F",       // ☀️
			'clear_night' => "\xF0\x9F\x8C\x99",          // 🌙
			'partly' => "\xE2\x9B\x85",                   // ⛅
			'partly_night' => "\xF0\x9F\x8C\x83",         // 🌃-ish use 🌤️ for day partial night: 🌙
			'cloudy' => "\xE2\x98\x81\xEF\xB8\x8F",      // ☁️
			'drizzle' => "\xE2\x98\x81\xEF\xB8\x8F",     // ☁️ base; + fog overlay
			'fog' => "\xF0\x9F\x8C\xAB\xEF\xB8\x8F",     // 🌫️
			'rain' => "\xF0\x9F\x8C\xA7\xEF\xB8\x8F",    // 🌧️
			'rain_heavy' => "\xF0\x9F\x8C\xA7\xEF\xB8\x8F",
			'showers' => "\xF0\x9F\x8C\xA6\xEF\xB8\x8F", // 🌦️
			'snow' => "\xE2\x9D\x84\xEF\xB8\x8F",        // ❄️
			'thunder' => "\xE2\x9A\xA1",                  // ⚡
			'later' => "\xC2\xB7\xC2\xB7\xC2\xB7",       // ···
			'unknown' => "\xE2\x98\x81\xEF\xB8\x8F",
		];
		$map['partly_night'] = "\xF0\x9F\x8C\x99"; // 🌙
		return isset($map[$sky]) ? $map[$sky] : $map['cloudy'];

	}

	/**
	 * Optional overlay key for complex icons.
	 * drizzle → fog (behind cloud); partly_night → cloud (in front of moon).
	 *
	 * @return string|null
	 */
	function sky_overlay($sky){

		$sky = preg_replace('/[^a-z0-9_]/', '', (string)$sky);
		if ($sky === 'drizzle'){
			return 'fog';
		}
		if ($sky === 'partly_night'){
			return 'cloud';
		}
		return null;

	}

	/**
	 * Glyph for overlay key.
	 */
	function sky_overlay_glyph($overlay){

		$overlay = preg_replace('/[^a-z0-9_]/', '', (string)$overlay);
		if ($overlay === 'fog'){
			return "\xF0\x9F\x8C\xAB\xEF\xB8\x8F"; // 🌫️
		}
		if ($overlay === 'cloud'){
			return "\xE2\x98\x81\xEF\xB8\x8F"; // ☁️
		}
		return '';

	}

	/**
	 * Condition class slug for .weather_sky_stack_* (style each sky separately).
	 * e.g. clear → full_sun, partly_night → partial_moon
	 */
	function sky_stack_slug($sky){

		$sky = preg_replace('/[^a-z0-9_]/', '', (string)$sky);
		$map = [
			'clear' => 'full_sun',
			'clear_night' => 'full_moon',
			'partly' => 'partial_sun',
			'partly_night' => 'partial_moon',
			'cloudy' => 'cloudy',
			'drizzle' => 'drizzle',
			'fog' => 'fog',
			'rain' => 'rain',
			'rain_heavy' => 'rain_heavy',
			'showers' => 'showers',
			'snow' => 'snow',
			'thunder' => 'thunder',
			'later' => 'later',
			'unknown' => 'unknown',
		];
		return isset($map[$sky]) ? $map[$sky] : 'unknown';

	}

	/**
	 * Stacked sky HTML: base + optional overlay (no CSS pseudo icons).
	 * Always includes weather_sky_stack_{slug} for per-condition CSS.
	 */
	function sky_html($sky){

		$sky = preg_replace('/[^a-z0-9_]/', '', (string)$sky);
		if ($sky === ''){
			$sky = 'cloudy';
		}
		$base = $this->sky_glyph($sky);
		$slug = $this->sky_stack_slug($sky);
		$stack_cls = 'weather_sky_stack weather_sky_stack_'.$slug;
		$overlay = $this->sky_overlay($sky);
		if ($overlay === null || $overlay === ''){
			return '<span class="'.$stack_cls.'"><span class="weather_sky_base">'.$base.'</span></span>';
		}
		$og = $this->sky_overlay_glyph($overlay);
		return '<span class="'.$stack_cls.' weather_sky_stack_has_overlay">'
			.'<span class="weather_sky_base">'.$base.'</span>'
			.'<span class="weather_sky_overlay">'.$og.'</span>'
			.'</span>';

	}

	/**
	 * Sky fields for API/JSON slots (glyph + optional overlay + stack slug).
	 *
	 * @return array{sky:string,sky_glyph:string,sky_overlay:?string,sky_overlay_glyph:string,sky_stack:string}
	 */
	function _sky_fields($sky){

		$sky = preg_replace('/[^a-z0-9_]/', '', (string)$sky);
		if ($sky === ''){
			$sky = 'cloudy';
		}
		$overlay = $this->sky_overlay($sky);
		return [
			'sky' => $sky,
			'sky_glyph' => $this->sky_glyph($sky),
			'sky_overlay' => $overlay,
			'sky_overlay_glyph' => $overlay ? $this->sky_overlay_glyph($overlay) : '',
			'sky_stack' => $this->sky_stack_slug($sky),
		];

	}

	/**
	 * Resolve provider → standardized forecast → weather_history → UI payload.
	 *
	 * @param bool $force_refresh ask provider to bypass its file-cache TTL
	 * @param string|null $provider_panel override (e.g. metoffice/forecast); null = weather settings
	 */
	function get_forecast($latitude, $longitude, $location = '', $force_refresh = false, $provider_panel = null){

		$lat = $this->_float_or($latitude, self::DEFAULT_LAT);
		$lon = $this->_float_or($longitude, self::DEFAULT_LON);
		if ($location === '' || $location === null){
			$location = 'Cheltenham';
		}

		$provider = $provider_panel !== null && $provider_panel !== ''
			? trim((string)$provider_panel)
			: $this->get_provider_panel();

		// Soft default so a fresh install still works before settings saved
		if ($provider === ''){
			$provider = 'openmeteo/forecast';
		}

		$raw = $this->_call_forecast_provider($provider, $lat, $lon, $force_refresh);
		$source = !empty($raw['source']) ? (string)$raw['source'] : 'unknown';

		if (empty($raw['ok']) || empty($raw['data']) || !is_array($raw['data'])){
			// If provider failed, still try history for UI
			$from_db = $this->_load_forecast_from_history($lat, $lon, $location);
			if (!empty($from_db['ok'])){
				$from_db['fetched_at'] = 0;
				$from_db['from_cache'] = 1;
				$from_db['source'] = $source;
				$from_db['error'] = !empty($raw['error']) ? (string)$raw['error'] : '';
				return $from_db;
			}
			return [
				'ok' => 0,
				'error' => !empty($raw['error']) ? $raw['error'] : 'Forecast request failed',
				'location' => $location,
				'latitude' => $lat,
				'longitude' => $lon,
				'fetched_at' => 0,
				'from_cache' => 0,
				'source' => $source,
				'days' => [],
				'later_days' => [],
				'hours_by_date' => new \stdClass(),
			];
		}

		$parsed = $this->_parse_payload($raw['data'], $location, $lat, $lon);
		$parsed['fetched_at'] = !empty($raw['fetched_at']) ? (int)$raw['fetched_at'] : 0;
		$parsed['from_cache'] = !empty($raw['from_cache']) ? 1 : 0;
		$parsed['source'] = $source;
		if (!empty($raw['attribution'])){
			$parsed['attribution'] = (string)$raw['attribution'];
		}

		// Persist / freeze into weather_history; UI reads DB as source of truth
		$this->_sync_weather_history($lat, $lon, $location, $parsed, $parsed['source']);
		$from_db = $this->_load_forecast_from_history($lat, $lon, $location);
		if (!empty($from_db['ok'])){
			$from_db['fetched_at'] = $parsed['fetched_at'];
			$from_db['from_cache'] = $parsed['from_cache'];
			$from_db['source'] = $parsed['source'];
			if (!empty($parsed['attribution'])){
				$from_db['attribution'] = $parsed['attribution'];
			}
			return $from_db;
		}

		return $parsed;

	}

	/**
	 * Stable location key for weather_history (portable multi-site).
	 */
	function location_key($lat, $lon, $location = ''){

		$lat = $this->_float_or($lat, self::DEFAULT_LAT);
		$lon = $this->_float_or($lon, self::DEFAULT_LON);
		return sprintf('%.2f_%.2f', $lat, $lon);

	}

	/**
	 * @return cms_db|null
	 */
	function _weather_db(){

		if (!empty($this->db) && is_object($this->db) && method_exists($this->db, 'query')){
			return $this->db;
		}
		if (function_exists('get_instance')){
			$CI = get_instance();
			if (!empty($CI->db) && is_object($CI->db)){
				return $CI->db;
			}
		}
		return null;

	}

	/**
	 * Upsert API hours into weather_history.
	 * Past complete hours: insert-once (never overwrite). Current+future: full upsert.
	 */
	function _sync_weather_history($lat, $lon, $location, array $parsed, $api_source){

		$db = $this->_weather_db();
		if ($db === null || !$db->table_exists('weather_history')){
			return;
		}

		$loc = $this->location_key($lat, $lon, $location);
		$now = time();
		$now_hour = $now - ($now % 3600);
		$api_source = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$api_source));
		if ($api_source === ''){
			$api_source = 'unknown';
		}

		$candidates = $this->_history_candidates_from_parsed($parsed, $api_source);
		// Daily plateau fill for sparse forecast days only (never invent past history)
		$candidates = $this->_history_apply_daily_fill($candidates, $parsed, $api_source, $now_hour);

		foreach ($candidates as $row){
			$h = (int)$row['hour_start'];
			if ($h <= 0){
				continue;
			}
			$is_past = ($h < $now_hour);
			$sky = !empty($row['sky']) ? preg_replace('/[^a-z0-9_]/', '', (string)$row['sky']) : '';
			$resolution = !empty($row['resolution']) ? preg_replace('/[^a-z0-9_]/', '', (string)$row['resolution']) : 'hourly';
			// Never freeze synthetic daily_fill into past hours (would stick forever via INSERT IGNORE)
			if ($is_past && $resolution === 'daily_fill'){
				continue;
			}
			$source = !empty($row['source']) ? preg_replace('/[^a-z0-9_\-]/', '', (string)$row['source']) : $api_source;
			$temp = isset($row['temp_c']) && $row['temp_c'] !== null ? (float)$row['temp_c'] : null;
			$code = isset($row['weather_code']) ? (int)$row['weather_code'] : null;
			$pop = isset($row['precip_prob']) ? (int)round((float)$row['precip_prob']) : null;
			if ($pop !== null){
				$pop = max(0, min(100, $pop));
			}
			$pmm = isset($row['precip_mm']) && $row['precip_mm'] !== null ? (float)$row['precip_mm'] : null;
			$wmph = isset($row['wind_mph']) && $row['wind_mph'] !== null ? (float)$row['wind_mph'] : null;
			$wdir = isset($row['wind_dir']) ? (int)$row['wind_dir'] : null;
			$is_day = isset($row['is_day']) ? (int)!empty($row['is_day']) : null;

			if ($is_past){
				// Insert-once: never overwrite existing past row
				$sql = 'INSERT IGNORE INTO weather_history '
					.'(location_key, hour_start, temp_c, weather_code, precip_prob, precip_mm, '
					.'wind_mph, wind_dir, is_day, sky, source, resolution, is_final, updated_at) '
					.'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)';
				$db->query($sql, [
					$loc, $h, $temp, $code, $pop, $pmm, $wmph, $wdir, $is_day, $sky, $source, $resolution, $now,
				]);
				// Mark existing non-final past rows final (clock rolled past)
				$db->query(
					'UPDATE weather_history SET is_final = 1 WHERE location_key = ? AND hour_start = ? AND is_final = 0',
					[$loc, $h]
				);
			} else {
				$sql = 'INSERT INTO weather_history '
					.'(location_key, hour_start, temp_c, weather_code, precip_prob, precip_mm, '
					.'wind_mph, wind_dir, is_day, sky, source, resolution, is_final, updated_at) '
					.'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?) '
					.'ON DUPLICATE KEY UPDATE '
					.'temp_c = VALUES(temp_c), weather_code = VALUES(weather_code), '
					.'precip_prob = VALUES(precip_prob), precip_mm = VALUES(precip_mm), '
					.'wind_mph = VALUES(wind_mph), wind_dir = VALUES(wind_dir), '
					.'is_day = VALUES(is_day), sky = VALUES(sky), source = VALUES(source), '
					.'resolution = VALUES(resolution), is_final = 0, updated_at = VALUES(updated_at)';
				$db->query($sql, [
					$loc, $h, $temp, $code, $pop, $pmm, $wmph, $wdir, $is_day, $sky, $source, $resolution, $now,
				]);
			}
		}

	}

	/**
	 * Flatten parsed hours_by_date into history candidate rows.
	 *
	 * @return list<array>
	 */
	function _history_candidates_from_parsed(array $parsed, $api_source){

		$out = [];
		$hours = !empty($parsed['hours_by_date']) ? $parsed['hours_by_date'] : [];
		if ($hours instanceof \stdClass){
			$hours = (array)$hours;
		}
		if (!is_array($hours)){
			return $out;
		}
		foreach ($hours as $date_key => $slots){
			if (!is_array($slots)){
				continue;
			}
			foreach ($slots as $sl){
				if (!is_array($sl) || !empty($sl['missing'])){
					continue;
				}
				if (!isset($sl['temp_c']) || $sl['temp_c'] === null){
					continue;
				}
				$ts = !empty($sl['ts']) ? (int)$sl['ts'] : 0;
				if ($ts <= 0){
					continue;
				}
				$ts = $ts - ($ts % 3600);
				$res = 'hourly';
				if (!empty($sl['resolution'])){
					$res = (string)$sl['resolution'];
				} else if (!empty($parsed['source']) && $parsed['source'] === 'metoffice'){
					// Met Office merge may be blend beyond native hourly — leave hourly unless flagged
					$res = 'hourly';
				}
				$out[$ts] = [
					'hour_start' => $ts,
					'temp_c' => (float)$sl['temp_c'],
					'weather_code' => isset($sl['weather_code']) ? (int)$sl['weather_code'] : 0,
					'precip_prob' => isset($sl['precip_prob']) ? (float)$sl['precip_prob'] : 0,
					'precip_mm' => null,
					'wind_mph' => isset($sl['wind_ms']) ? (float)$sl['wind_ms'] : null, // field stores mph after unit choice
					'wind_dir' => isset($sl['wind_dir']) ? (int)$sl['wind_dir'] : null,
					'is_day' => !empty($sl['is_day']) ? 1 : 0,
					'sky' => !empty($sl['sky']) ? $sl['sky'] : '',
					'source' => $api_source,
					'resolution' => $res,
				];
			}
		}
		return $out;

	}

	/**
	 * For calendar days with daily max/min, fill missing hours with day/night plateaus
	 * (09–20 local = day high; 21–08 local = night low). Does not replace richer candidates.
	 * Never invents past hours — those only come from real API slots (freeze via INSERT IGNORE).
	 *
	 * @param array<int,array> $candidates keyed by hour_start
	 * @param int $now_hour current hour start (unix); past hours skipped
	 * @return array<int,array>
	 */
	function _history_apply_daily_fill(array $candidates, array $parsed, $api_source, $now_hour = 0){

		// Use day rows already computed
		$days = !empty($parsed['days']) && is_array($parsed['days']) ? $parsed['days'] : [];
		$later = !empty($parsed['later_days']) && is_array($parsed['later_days']) ? $parsed['later_days'] : [];
		$all_days = array_merge($days, $later);
		if (empty($all_days)){
			return $candidates;
		}

		$now_hour = (int)$now_hour;
		if ($now_hour <= 0){
			$now = time();
			$now_hour = $now - ($now % 3600);
		}

		$tz = new \DateTimeZone('Europe/London');
		foreach ($all_days as $day){
			if (empty($day['date'])){
				continue;
			}
			$date_key = substr((string)$day['date'], 0, 10);
			$tmax = isset($day['temp_max']) && $day['temp_max'] !== null ? (float)$day['temp_max'] : null;
			$tmin = isset($day['temp_min']) && $day['temp_min'] !== null ? (float)$day['temp_min'] : null;
			if ($tmax === null && $tmin === null){
				continue;
			}
			$code = !empty($day['weather_code']) ? (int)$day['weather_code'] : 3;
			$sky_day = !empty($day['sky']) ? $day['sky'] : $this->_sky_key($code, 1);
			$sky_night = $this->_sky_key($code === 0 ? 0 : ($code <= 2 ? 2 : $code), 0);
			if ($sky_night === 'partly'){
				$sky_night = 'partly_night';
			}
			if ($sky_night === 'clear'){
				$sky_night = 'clear_night';
			}
			$pop = isset($day['precip_prob_max']) ? (float)$day['precip_prob_max'] : 0;

			$start = \DateTime::createFromFormat('Y-m-d H:i:s', $date_key.' 00:00:00', $tz);
			if (!$start){
				continue;
			}
			for ($h = 0; $h < 24; $h++){
				$dt = clone $start;
				$dt->setTime($h, 0, 0);
				$ts = $dt->getTimestamp();
				$ts = $ts - ($ts % 3600);
				if ($ts < $now_hour){
					continue; // do not invent past history
				}
				if (isset($candidates[$ts])){
					continue; // richer data wins
				}
				// 09:00–20:59 day plateau; 21:00–08:59 night plateau
				$is_day_plateau = ($h >= 9 && $h <= 20);
				$temp = $is_day_plateau
					? ($tmax !== null ? $tmax : $tmin)
					: ($tmin !== null ? $tmin : $tmax);
				if ($temp === null){
					continue;
				}
				$candidates[$ts] = [
					'hour_start' => $ts,
					'temp_c' => $temp,
					'weather_code' => $code,
					'precip_prob' => $pop,
					'precip_mm' => null,
					'wind_mph' => null,
					'wind_dir' => null,
					'is_day' => $is_day_plateau ? 1 : 0,
					'sky' => $is_day_plateau ? $sky_day : $sky_night,
					'source' => $api_source,
					'resolution' => 'daily_fill',
				];
			}
		}
		return $candidates;

	}

	/**
	 * Build UI forecast payload from weather_history (source of truth).
	 *
	 * @return array{ok:int, ...}
	 */
	function _load_forecast_from_history($lat, $lon, $location){

		$db = $this->_weather_db();
		if ($db === null || !$db->table_exists('weather_history')){
			return ['ok' => 0];
		}

		$loc = $this->location_key($lat, $lon, $location);
		$tz = new \DateTimeZone('Europe/London');
		$now = new \DateTime('now', $tz);
		$current_wd = $this->weather_day_key($now);
		// Load from start of current weather day (06:00) through ~10 days
		$start_dt = \DateTime::createFromFormat('Y-m-d H:i:s', $current_wd.' 06:00:00', $tz);
		if (!$start_dt){
			return ['ok' => 0];
		}
		$range_start = $start_dt->getTimestamp();
		$range_end = $range_start + (10 * 86400);

		$sql = 'SELECT hour_start, temp_c, weather_code, precip_prob, precip_mm, wind_mph, wind_dir, is_day, sky, source, resolution '
			.'FROM weather_history WHERE location_key = ? AND hour_start >= ? AND hour_start < ? ORDER BY hour_start ASC';
		$q = $db->query($sql, [$loc, $range_start, $range_end]);
		if ($q === false){
			return ['ok' => 0];
		}
		$rows = $q->result_array();
		if (empty($rows)){
			return ['ok' => 0];
		}

		// Fake Open-Meteo-shaped arrays and reuse _parse_payload
		$times = [];
		$temps = [];
		$codes = [];
		$pops = [];
		$precs = [];
		$clouds = [];
		$vis = [];
		$winds = [];
		$dirs = [];
		$is_days = [];
		foreach ($rows as $r){
			$ts = (int)$r['hour_start'];
			$dt = new \DateTime('@'.$ts);
			$dt->setTimezone($tz);
			$times[] = $dt->format('Y-m-d\TH:i');
			$temps[] = $r['temp_c'] !== null ? (float)$r['temp_c'] : null;
			$codes[] = $r['weather_code'] !== null ? (int)$r['weather_code'] : 0;
			$pops[] = $r['precip_prob'] !== null ? (float)$r['precip_prob'] : 0;
			$precs[] = $r['precip_mm'] !== null ? (float)$r['precip_mm'] : 0;
			$clouds[] = 50;
			$vis[] = 20000;
			$winds[] = $r['wind_mph'] !== null ? (float)$r['wind_mph'] : 0;
			$dirs[] = $r['wind_dir'] !== null ? (float)$r['wind_dir'] : 0;
			$is_days[] = !empty($r['is_day']) ? 1 : 0;
		}

		$data = [
			'latitude' => $lat,
			'longitude' => $lon,
			'timezone' => 'Europe/London',
			'_source' => 'weather_history',
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
				'is_day' => $is_days,
			],
			'daily' => [
				'time' => [],
				'temperature_2m_max' => [],
				'temperature_2m_min' => [],
				'weather_code' => [],
				'precipitation_probability_max' => [],
			],
		];

		return $this->_parse_payload($data, $location, $lat, $lon);

	}

	function _card_temp_max(array $calendar_hours, $date_key){

		return $this->_card_temp_extremum($calendar_hours, $date_key, 3, 2, true);

	}

	/**
	 * Day-card minimum: min temp from 15:00 on $date_key through 14:00 next calendar day.
	 * (BBC-style — coming night min, not previous night)
	 *
	 * @param array<string,array<int,float>> $calendar_hours
	 * @return int|null
	 */
	function _card_temp_min(array $calendar_hours, $date_key){

		return $this->_card_temp_extremum($calendar_hours, $date_key, 15, 14, false);

	}

	/**
	 * @param array<string,array<int,float>> $calendar_hours
	 * @param string $date_key Y-m-d label day
	 * @param int $start_hour inclusive hour on date_key
	 * @param int $end_hour_next inclusive hour on date_key+1
	 * @param bool $want_max true = max, false = min
	 * @return int|null
	 */
	function _card_temp_extremum(array $calendar_hours, $date_key, $start_hour, $end_hour_next, $want_max){

		$tz = new \DateTimeZone('Europe/London');
		$start = \DateTime::createFromFormat('Y-m-d H:i', $date_key.' 00:00', $tz);
		if (!$start){
			return null;
		}
		$next = clone $start;
		$next->modify('+1 day');
		$next_key = $next->format('Y-m-d');

		$vals = [];
		// Hours start_hour … 23 on label day
		for ($h = $start_hour; $h < 24; $h++){
			if (isset($calendar_hours[$date_key][$h])){
				$vals[] = (float)$calendar_hours[$date_key][$h];
			}
		}
		// Hours 0 … end_hour_next on next calendar day
		for ($h = 0; $h <= $end_hour_next; $h++){
			if (isset($calendar_hours[$next_key][$h])){
				$vals[] = (float)$calendar_hours[$next_key][$h];
			}
		}
		if (empty($vals)){
			return null;
		}
		return (int)round($want_max ? max($vals) : min($vals));

	}

	/**
	 * Weather-day key for a London DateTime: day starts at 06:00.
	 * 00:00–05:59 belong to the previous calendar date’s weather day.
	 */
	function weather_day_key($dt){

		if ($dt instanceof \DateTimeImmutable){
			$local = \DateTime::createFromImmutable($dt);
		} elseif ($dt instanceof \DateTime){
			$local = clone $dt;
		} else {
			$local = new \DateTime('now', new \DateTimeZone('Europe/London'));
		}
		$local->setTimezone(new \DateTimeZone('Europe/London'));
		$hour = (int)$local->format('G');
		if ($hour < self::DAY_START_HOUR){
			$local->modify('-1 day');
		}
		return $local->format('Y-m-d');

	}

	/**
	 * Clock hours in display order for one weather day: 06…23, 00…05.
	 *
	 * @return list<int>
	 */
	function _weather_day_hour_order(){

		$order = [];
		for ($h = self::DAY_START_HOUR; $h < 24; $h++){
			$order[] = $h;
		}
		for ($h = 0; $h < self::DAY_START_HOUR; $h++){
			$order[] = $h;
		}
		return $order;

	}

	function _empty_hour_slot($h){

		$sky = $this->_sky_fields('unknown');
		return [
			'ts' => 0,
			'hour' => $h,
			'temp_c' => null,
			'weather_code' => 0,
			'sky' => $sky['sky'],
			'sky_glyph' => $sky['sky_glyph'],
			'sky_overlay' => $sky['sky_overlay'],
			'sky_overlay_glyph' => $sky['sky_overlay_glyph'],
			'precip_kind' => 'none',
			'precip_level' => 0,
			'precip_prob' => 0,
			'wind_ms' => null,
			'wind_dir' => 0,
			'wind_compass' => 'N',
			'wind_flags' => 0,
			'is_day' => ($h >= 6 && $h < 20) ? 1 : 0,
			'missing' => 1,
		];

	}

	function _parse_payload(array $data, $location, $lat, $lon){

		$hourly = !empty($data['hourly']) ? $data['hourly'] : [];
		$daily = !empty($data['daily']) ? $data['daily'] : [];
		$times = !empty($hourly['time']) && is_array($hourly['time']) ? $hourly['time'] : [];
		$tz = new \DateTimeZone('Europe/London');

		// Bucket by BBC weather day (06:00 start); also keep calendar index for card min/max
		$hours_by_date = [];
		$calendar_hours = []; // [Y-m-d][hour] => temp_c — true calendar clock, not weather-day
		$n = count($times);
		for ($i = 0; $i < $n; $i++){
			$iso = $times[$i];
			$ts = strtotime($iso.' Europe/London');
			if ($ts === false){
				$ts = strtotime($iso);
			}
			if ($ts === false){
				continue;
			}

			$dt = new \DateTime('@'.$ts);
			$dt->setTimezone($tz);
			$hour = (int)$dt->format('G');
			$cal_date = $dt->format('Y-m-d');
			// 0–5am → previous calendar date’s weather day
			$date_key = $this->weather_day_key($dt);

			$code = isset($hourly['weather_code'][$i]) ? (int)$hourly['weather_code'][$i] : 0;
			$prob = isset($hourly['precipitation_probability'][$i])
				? (float)$hourly['precipitation_probability'][$i] : 0.0;
			// Open-Meteo may emit null beyond model range — skip empty hours
			$temp_raw = $hourly['temperature_2m'][$i] ?? null;
			if ($temp_raw === null || $temp_raw === ''){
				continue;
			}
			$temp = (float)$temp_raw;
			$wind_ms = isset($hourly['wind_speed_10m'][$i]) && $hourly['wind_speed_10m'][$i] !== null
				? (float)$hourly['wind_speed_10m'][$i] : 0.0;
			$wind_dir = isset($hourly['wind_direction_10m'][$i]) && $hourly['wind_direction_10m'][$i] !== null
				? (float)$hourly['wind_direction_10m'][$i] : 0.0;
			$is_day = !empty($hourly['is_day'][$i]) ? 1 : 0;
			$vis = isset($hourly['visibility'][$i]) && $hourly['visibility'][$i] !== null
				? (float)$hourly['visibility'][$i] : null;

			$precip_kind = $this->_precip_kind($code, $prob, $vis);
			$precip_level = $this->_precip_level($prob, $precip_kind);
			$sky_f = $this->_sky_fields($this->_sky_key($code, $is_day));

			$slot = [
				'ts' => $ts,
				'hour' => $hour,
				'temp_c' => $temp,
				'weather_code' => $code,
				'sky' => $sky_f['sky'],
				'sky_glyph' => $sky_f['sky_glyph'],
				'sky_overlay' => $sky_f['sky_overlay'],
				'sky_overlay_glyph' => $sky_f['sky_overlay_glyph'],
				'precip_kind' => $precip_kind,
				'precip_level' => $precip_level,
				'precip_prob' => $prob,
				'wind_ms' => round($wind_ms, 1),
				'wind_dir' => (int)round($wind_dir),
				'wind_compass' => $this->_compass8($wind_dir),
				'wind_flags' => $this->_wind_flags($wind_ms),
				'is_day' => $is_day,
			];

			if (!isset($hours_by_date[$date_key])){
				$hours_by_date[$date_key] = [];
			}
			$hours_by_date[$date_key][$hour] = $slot;

			if (!isset($calendar_hours[$cal_date])){
				$calendar_hours[$cal_date] = [];
			}
			$calendar_hours[$cal_date][$hour] = $temp;
		}

		// Normalise each weather day to 24 slots in 06…23,00…05 order
		$hour_order = $this->_weather_day_hour_order();
		foreach ($hours_by_date as $date_key => $by_hour){
			$full = [];
			foreach ($hour_order as $h){
				if (isset($by_hour[$h])){
					$full[] = $by_hour[$h];
				} else {
					$full[] = $this->_empty_hour_slot($h);
				}
			}
			$hours_by_date[$date_key] = $full;
		}

		// Day chips: one per weather-day key (sorted), highs/lows from that day's hours
		// Prefer order of Open-Meteo daily dates when present, else sort keys
		$day_keys = [];
		$d_times = !empty($daily['time']) && is_array($daily['time']) ? $daily['time'] : [];
		foreach ($d_times as $d_iso){
			$dk = substr($d_iso, 0, 10);
			if ($dk !== '' && !in_array($dk, $day_keys, true)){
				$day_keys[] = $dk;
			}
		}
		foreach (array_keys($hours_by_date) as $dk){
			if (!in_array($dk, $day_keys, true)){
				$day_keys[] = $dk;
			}
		}
		sort($day_keys);

		// Never show past weather days — chips always start at current BBC day (06:00 boundary)
		$current_wd = $this->weather_day_key(new \DateTime('now', $tz));
		$day_keys = array_values(array_filter($day_keys, function($dk) use ($current_wd){
			return strcmp($dk, $current_wd) >= 0;
		}));
		// Drop hours for past weather days from payload (keep UI lean)
		foreach (array_keys($hours_by_date) as $dk){
			if (strcmp($dk, $current_wd) < 0){
				unset($hours_by_date[$dk]);
			}
		}

		// Daily API row by calendar date (for sky code fallback)
		$daily_by_date = [];
		$d_n = count($d_times);
		for ($i = 0; $i < $d_n; $i++){
			$dk = substr($d_times[$i], 0, 10);
			$daily_by_date[$dk] = [
				'weather_code' => isset($daily['weather_code'][$i]) ? (int)$daily['weather_code'][$i] : 0,
				'temp_max' => isset($daily['temperature_2m_max'][$i]) ? (float)$daily['temperature_2m_max'][$i] : null,
				'temp_min' => isset($daily['temperature_2m_min'][$i]) ? (float)$daily['temperature_2m_min'][$i] : null,
				'precip_prob_max' => isset($daily['precipitation_probability_max'][$i])
					? (float)$daily['precipitation_probability_max'][$i] : 0.0,
			];
		}

		$day_rows = [];
		foreach ($day_keys as $date_key){
			$dt = \DateTime::createFromFormat('Y-m-d', $date_key, $tz);
			if (!$dt){
				continue;
			}
			$hours = isset($hours_by_date[$date_key]) ? $hours_by_date[$date_key] : [];
			// Ensure hours array exists even if only daily row
			if (empty($hours)){
				$hours = [];
				foreach ($hour_order as $h){
					$hours[] = $this->_empty_hour_slot($h);
				}
				$hours_by_date[$date_key] = $hours;
			}

			// Skip sparse days (model tail) — need enough real hourly temps
			$real_n = 0;
			foreach ($hours as $sl){
				if (isset($sl['temp_c']) && $sl['temp_c'] !== null && empty($sl['missing'])){
					$real_n++;
				}
			}
			// Met Office hourly starts at “now” — first weather-day may have <24 hours
			if ($real_n < 6){
				continue;
			}

			$code = 0;
			$prob = 0.0;
			foreach ($hours as $sl){
				if (!empty($sl['weather_code'])){
					$code = (int)$sl['weather_code'];
				}
				if (!empty($sl['precip_prob']) && (float)$sl['precip_prob'] > $prob){
					$prob = (float)$sl['precip_prob'];
				}
			}
			// Fallback sky/pop from daily API
			if (isset($daily_by_date[$date_key])){
				if ($code === 0 && !empty($daily_by_date[$date_key]['weather_code'])){
					$code = (int)$daily_by_date[$date_key]['weather_code'];
				}
				if ($prob <= 0 && !empty($daily_by_date[$date_key]['precip_prob_max'])){
					$prob = (float)$daily_by_date[$date_key]['precip_prob_max'];
				}
			}

			// BBC day-card highs/lows (not same as weather-day 06–05 strip):
			// max: 03:00 this calendar day → 02:00 next calendar day
			// min: 15:00 this calendar day → 14:00 next calendar day (coming night)
			$tmax = $this->_card_temp_max($calendar_hours, $date_key);
			$tmin = $this->_card_temp_min($calendar_hours, $date_key);
			// Fallback if windows incomplete
			if ($tmax === null && isset($daily_by_date[$date_key]['temp_max'])){
				$tmax = $daily_by_date[$date_key]['temp_max'] !== null
					? (int)round((float)$daily_by_date[$date_key]['temp_max']) : null;
			}
			if ($tmin === null && isset($daily_by_date[$date_key]['temp_min'])){
				$tmin = $daily_by_date[$date_key]['temp_min'] !== null
					? (int)round((float)$daily_by_date[$date_key]['temp_min']) : null;
			}
			// Prefer midday-ish icon for day card
			$sky_day = $this->_sky_key($code, 1);
			foreach ($hours as $sl){
				if (isset($sl['hour']) && (int)$sl['hour'] === 12 && !empty($sl['sky']) && empty($sl['missing'])){
					$sky_day = $sl['sky'];
					break;
				}
			}
			$sky_day_f = $this->_sky_fields($sky_day);

			$day_rows[] = [
				'date' => $date_key,
				'label_dow' => $dt->format('D'),
				'label_day' => $dt->format('d'),
				'label_ord' => $this->_ordinal((int)$dt->format('j')),
				'label_chip' => $dt->format('D d'),
				'weather_code' => $code,
				'sky' => $sky_day_f['sky'],
				'sky_glyph' => $sky_day_f['sky_glyph'],
				'sky_overlay' => $sky_day_f['sky_overlay'],
				'sky_overlay_glyph' => $sky_day_f['sky_overlay_glyph'],
				'temp_max' => $tmax,
				'temp_min' => $tmin,
				'precip_prob_max' => $prob,
				'blocks_6h' => $this->_six_hour_blocks($hours),
			];
		}

		$days = array_slice($day_rows, 0, self::DAY_CHIP_COUNT);
		$later_days = array_slice($day_rows, self::DAY_CHIP_COUNT);

		$hours_obj = $hours_by_date;
		if (empty($hours_obj)){
			$hours_obj = new \stdClass();
		}

		$ok = !empty($days) || !empty($hours_by_date) ? 1 : 0;

		return [
			'ok' => $ok,
			'error' => $ok ? '' : 'No forecast data',
			'location' => $location,
			'latitude' => $lat,
			'longitude' => $lon,
			'days' => $days,
			'later_days' => $later_days,
			'hours_by_date' => $hours_obj,
			// For panel default selection (same key used to filter past days)
			'current_weather_day' => $current_wd,
		];

	}

	/**
	 * Aggregate hourly into 06–11, 12–17, 18–23, 00–05 (weather-day order).
	 * $hours is a list of 24 slots in that order (not keyed by hour).
	 */
	function _six_hour_blocks(array $hours){

		// Index by clock hour for lookup
		$by_h = [];
		foreach ($hours as $sl){
			if (isset($sl['hour'])){
				$by_h[(int)$sl['hour']] = $sl;
			}
		}

		$block_starts = [6, 12, 18, 0];
		$blocks = [];
		foreach ($block_starts as $start){
			$temps = [];
			$code = 0;
			$prob = 0;
			$wind = 0;
			$dir = 0;
			$is_day = ($start === 6 || $start === 12) ? 1 : 0;
			for ($i = 0; $i < 6; $i++){
				$h = ($start + $i) % 24;
				if (!isset($by_h[$h])){
					continue;
				}
				$sl = $by_h[$h];
				if (isset($sl['temp_c']) && $sl['temp_c'] !== null && empty($sl['missing'])){
					$temps[] = $sl['temp_c'];
				}
				if (!empty($sl['weather_code'])){
					$code = (int)$sl['weather_code'];
				}
				if (!empty($sl['precip_prob']) && $sl['precip_prob'] > $prob){
					$prob = (float)$sl['precip_prob'];
				}
				if (isset($sl['wind_ms']) && $sl['wind_ms'] !== null && $sl['wind_ms'] > $wind){
					$wind = (float)$sl['wind_ms'];
					$dir = (int)$sl['wind_dir'];
				}
				if (isset($sl['is_day'])){
					$is_day = (int)$sl['is_day'];
				}
			}
			$kind = $this->_precip_kind($code, $prob, null);
			$sky_b = $this->_sky_fields($this->_sky_key($code, $is_day));
			$blocks[] = [
				'hour_start' => $start,
				'label' => sprintf('%02d', $start),
				'temp_c' => $temps ? round(array_sum($temps) / count($temps), 1) : null,
				'temp_max' => $temps ? round(max($temps)) : null,
				'temp_min' => $temps ? round(min($temps)) : null,
				'sky' => $sky_b['sky'],
				'sky_glyph' => $sky_b['sky_glyph'],
				'sky_overlay' => $sky_b['sky_overlay'],
				'sky_overlay_glyph' => $sky_b['sky_overlay_glyph'],
				'weather_code' => $code,
				'precip_kind' => $kind,
				'precip_level' => $this->_precip_level($prob, $kind),
				'precip_prob' => $prob,
				'wind_ms' => $wind ? round($wind, 1) : null,
				'wind_dir' => $dir,
				'wind_compass' => $this->_compass8($dir),
				'wind_flags' => $this->_wind_flags($wind),
			];
		}
		return $blocks;

	}

	function _precip_kind($code, $prob, $visibility_m){

		$code = (int)$code;
		if (in_array($code, self::FOG_CODES, true)){
			return 'fog';
		}
		// Low visibility heuristic if code unclear
		if ($visibility_m !== null && $visibility_m > 0 && $visibility_m < 1000 && $code < 50){
			return 'fog';
		}
		if (in_array($code, self::SNOW_CODES, true)){
			return 'snow';
		}
		// Freezing rain / sleet still “rain-like” drops; snow symbols only for snow codes
		if ($prob < 10 && $code < 51){
			return 'none';
		}
		return 'rain';

	}

	/**
	 * 0 none, 1 light (10–40%), 3 heavy (>40%). Fog uses level 1–3 as density of dots.
	 */
	function _precip_level($prob, $kind){

		if ($kind === 'none'){
			return 0;
		}
		$prob = (float)$prob;
		if ($kind === 'fog'){
			if ($prob > 40){
				return 3;
			}
			return $prob >= 10 ? 1 : 1; // always show some dots for fog codes
		}
		if ($prob > 40){
			return 3;
		}
		if ($prob >= 10){
			return 1;
		}
		// Code says precip but low prob — still show 1 mark
		if ($kind === 'rain' || $kind === 'snow'){
			return 1;
		}
		return 0;

	}

	/**
	 * Coarse sky icon key for CSS classes.
	 */
	function _sky_key($code, $is_day){

		$code = (int)$code;
		$night = empty($is_day);
		if ($code === 0){
			return $night ? 'clear_night' : 'clear';
		}
		if ($code === 1 || $code === 2){
			return $night ? 'partly_night' : 'partly';
		}
		if ($code === 3){
			return 'cloudy';
		}
		if (in_array($code, self::FOG_CODES, true)){
			return 'fog';
		}
		// Light/moderate drizzle (incl. freezing) → cloud + fog overlay, not full rain
		if ($code >= 51 && $code <= 57){
			return 'drizzle';
		}
		if ($code >= 61 && $code <= 67){
			return $code >= 65 ? 'rain_heavy' : 'rain';
		}
		if (in_array($code, self::SNOW_CODES, true) || ($code >= 71 && $code <= 77)){
			return 'snow';
		}
		if ($code >= 80 && $code <= 82){
			return 'showers';
		}
		if ($code >= 85 && $code <= 86){
			return 'snow';
		}
		if ($code >= 95){
			return 'thunder';
		}
		return 'cloudy';

	}

	/**
	 * 8-point compass from degrees (meteorological: direction wind comes FROM).
	 */
	function _compass8($deg){

		$deg = fmod((float)$deg, 360.0);
		if ($deg < 0){
			$deg += 360.0;
		}
		$dirs = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
		$idx = (int)floor(fmod($deg + 22.5, 360.0) / 45.0);
		if ($idx < 0){
			$idx = 0;
		}
		if ($idx > 7){
			$idx = 7;
		}
		return $dirs[$idx];

	}

	/**
	 * Wind barb half-flags when speed is in mph (BBC unit).
	 * Each half-flag ≈ 5 mph; full flag = 2 halves.
	 */
	function _wind_flags($mph){

		$mph = max(0.0, (float)$mph);
		$half = (int)round($mph / 5.0);
		return min(10, max(0, $half));

	}

	function _ordinal($n){

		$n = (int)$n;
		$mod100 = $n % 100;
		if ($mod100 >= 11 && $mod100 <= 13){
			return $n.'th';
		}
		switch ($n % 10){
			case 1: return $n.'st';
			case 2: return $n.'nd';
			case 3: return $n.'rd';
			default: return $n.'th';
		}

	}

	function _float_or($v, $default){

		if ($v === '' || $v === null){
			return (float)$default;
		}
		if (!is_numeric($v)){
			return (float)$default;
		}
		return (float)$v;

	}

}
