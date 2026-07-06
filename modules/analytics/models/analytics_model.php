<?php defined('BASEPATH') OR exit('No direct script access allowed');

class analytics_model extends Model {

	private $_geo_cache = null;
	private $_geo_cache_dirty = false;
	private $_php_pageview_grace_seconds = 30;
	private $_php_pageview_match_seconds = 30;
	private $_bot_pageview_purge_seconds = 300;

	function get_total_pageviews() {

		$row = $this->db->query('SELECT COUNT(*) AS cnt FROM cms_analytics_pageview WHERE bot = 0')->row_array();
		return !empty($row['cnt']) ? (int)$row['cnt'] : 0;

	}

	function get_total_sessions() {

		return (int)$this->db->count_all('cms_analytics_session');

	}

	function get_pageviews_last_30_days() {

		$row = $this->db->query('SELECT COUNT(*) AS cnt FROM cms_analytics_pageview WHERE bot = 0 AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->row_array();
		return !empty($row['cnt']) ? (int)$row['cnt'] : 0;

	}

	function get_sessions_last_30_days() {

		$row = $this->db->query('SELECT COUNT(*) AS cnt FROM cms_analytics_session WHERE started >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->row_array();
		return !empty($row['cnt']) ? (int)$row['cnt'] : 0;

	}

	function get_pageviews_last_7_days() {

		$row = $this->db->query('SELECT COUNT(*) AS cnt FROM cms_analytics_pageview WHERE bot = 0 AND created >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->row_array();
		return !empty($row['cnt']) ? (int)$row['cnt'] : 0;

	}

	function record_php_pageview($page) {

		$this->load->helper('analytics/analytics_api_helper');

		if (!analytics_beacon_enabled() || analytics_is_bot()) {
			return '';
		}

		if (!$this->_php_pageview_table_ready()) {
			return '';
		}

		$page = analytics_normalise_page($page);
		if ($page === '') {
			return '';
		}

		$beacon_id = analytics_resolve_beacon_id_for_php();
		analytics_persist_beacon_id_for_php($beacon_id);

		$dedupe = $this->db->query('SELECT cms_analytics_pageview_php_id FROM cms_analytics_pageview_php WHERE beacon_id = ? AND page = ? AND created >= DATE_SUB(NOW(), INTERVAL 2 SECOND) LIMIT 1',
				array($beacon_id, $page))->row_array();
		if (!empty($dedupe['cms_analytics_pageview_php_id'])) {
			return $beacon_id;
		}

		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$ip_anonymised = analytics_store_ip($ip);
		$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
		$language = analytics_get_beacon_language();
		$pageview_token = analytics_generate_pageview_token();

		$this->db->query('INSERT INTO cms_analytics_pageview_php (pageview_token, beacon_id, page, language, ip_anonymised, user_agent, created) VALUES (?, ?, ?, ?, ?, ?, NOW())',
				array($pageview_token, $beacon_id, $page, $language, $ip_anonymised, $user_agent));

		return $beacon_id;

	}

	function get_sessions_last_7_days() {

		$row = $this->db->query('SELECT COUNT(*) AS cnt FROM cms_analytics_session WHERE started >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->row_array();
		return !empty($row['cnt']) ? (int)$row['cnt'] : 0;

	}

	function get_last_pageviews($limit = 50) {

		$limit = (int)$limit;
		$query = $this->db->query('SELECT * FROM cms_analytics_pageview ORDER BY created DESC LIMIT '.$limit);
		return $query->result_array();

	}

	function get_top_pages($limit = 20) {

		$limit = (int)$limit;
		$query = $this->db->query('SELECT page, COUNT(*) AS pageviews, ROUND(AVG(seconds)) AS avg_seconds, ROUND(AVG(scroll_pct)) AS avg_scroll FROM cms_analytics_pageview WHERE bot = 0 GROUP BY page ORDER BY pageviews DESC LIMIT '.$limit);
		return $query->result_array();

	}

	function get_last_sessions($limit = 50) {

		$limit = (int)$limit;
		$query = $this->db->query('SELECT * FROM cms_analytics_session ORDER BY last_activity DESC LIMIT '.$limit);
		return $query->result_array();

	}

	function delete_visitor_from_dashboard($row_type, $row_id) {

		$this->load->helper('analytics/analytics_api_helper');

		$visitor_id = $this->_resolve_visitor_id_for_dashboard_delete($row_type, $row_id);
		if ($visitor_id === '') {
			return false;
		}

		return $this->delete_visitor_data($visitor_id);

	}

	function delete_visitor_data($visitor_id) {

		$this->load->helper('analytics/analytics_api_helper');

		if (!analytics_is_valid_session_id($visitor_id)) {
			return false;
		}

		$this->db->query('DELETE FROM cms_analytics_pageview WHERE session_id = ? OR beacon_id = ?',
				array($visitor_id, $visitor_id));
		$this->db->query('DELETE FROM cms_analytics_session WHERE session_id = ? LIMIT 1', array($visitor_id));

		if ($this->_php_pageview_table_ready()) {
			$this->db->query('DELETE FROM cms_analytics_pageview_php WHERE beacon_id = ?', array($visitor_id));
		}

		return true;

	}

	private function _resolve_visitor_id_for_dashboard_delete($row_type, $row_id) {

		$this->load->helper('analytics/analytics_api_helper');

		$row_type = trim((string)$row_type);
		$row_id = trim((string)$row_id);

		if ($row_type === 'session') {
			return analytics_is_valid_session_id($row_id) ? $row_id : '';
		}

		if ($row_type === 'pageview') {
			$pageview_id = (int)$row_id;
			if ($pageview_id < 1) {
				return '';
			}

			$row = $this->db->query('SELECT session_id, beacon_id FROM cms_analytics_pageview WHERE cms_analytics_pageview_id = ? LIMIT 1',
					array($pageview_id))->row_array();
			if (empty($row)) {
				return '';
			}

			if (!empty($row['session_id']) && analytics_is_valid_session_id($row['session_id'])) {
				return $row['session_id'];
			}

			if (!empty($row['beacon_id']) && analytics_is_valid_session_id($row['beacon_id'])) {
				return $row['beacon_id'];
			}
		}

		return '';

	}

	function get_geo_top($limit = 50) {

		$limit = (int)$limit;
		$query = $this->db->query('SELECT country, region, COUNT(*) AS sessions FROM cms_analytics_session WHERE country IS NOT NULL AND country != "" GROUP BY country, region ORDER BY sessions DESC LIMIT '.$limit);
		return $query->result_array();

	}

	function site_has_multiple_languages() {

		if (empty($GLOBALS['config']['targets_enabled'])) {
			return false;
		}

		$this->load->model('cms/cms_page_panel_model');
		$targets = $this->cms_page_panel_model->get_cms_page_panel_settings('cms/cms_targets');
		$groups = $targets['groups'] ?? [];

		if (!is_array($groups)) {
			return false;
		}

		foreach ($groups as $group) {
			if (($group['heading'] ?? '') !== 'language' || ($group['strategy'] ?? '') !== 'language') {
				continue;
			}
			$ids = array_filter(array_map('trim', explode('|', $group['settings'] ?? '')));
			return count($ids) > 1;
		}

		return false;

	}

	function get_show_geoip_debug() {

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('analytics/analytics_settings');

		return !empty($settings['show_geoip_debug']);

	}

	function process_analytics_batch($limit = 500) {

		$this->load->helper('analytics/analytics_api_helper');

		$limit = (int)$limit;
		$processed = $this->_process_php_pageviews_batch($limit);

		$this->db->query('UPDATE cms_analytics_pageview SET beacon_id = session_id WHERE session_id != "" AND beacon_id = ""');

		$query = $this->db->query('SELECT DISTINCT beacon_id FROM cms_analytics_pageview WHERE beacon_id != "" AND session_id = "" AND bot = 0 LIMIT '.$limit);
		foreach ($query->result_array() as $row) {
			if ($this->_sync_session($row['beacon_id'], true)) {
				$processed++;
			}
		}

		$query = $this->db->query('SELECT DISTINCT p.session_id FROM cms_analytics_pageview p LEFT JOIN cms_analytics_session s ON p.session_id = s.session_id WHERE p.session_id != "" AND s.session_id IS NULL AND p.bot = 0 LIMIT '.$limit);
		foreach ($query->result_array() as $row) {
			if ($this->_sync_session($row['session_id'], false)) {
				$processed++;
			}
		}

		$query = $this->db->query('SELECT s.session_id FROM cms_analytics_session s INNER JOIN (SELECT session_id, MAX(updated) AS max_updated FROM cms_analytics_pageview WHERE session_id != "" AND bot = 0 GROUP BY session_id) p ON p.session_id = s.session_id WHERE p.max_updated > s.last_activity LIMIT '.$limit);
		foreach ($query->result_array() as $row) {
			if ($this->_sync_session($row['session_id'], false)) {
				$processed++;
			}
		}

		if ($this->geoip_lookup_available()) {
			$query = $this->db->query('SELECT session_id, ip_anonymised, country, geo_resolved FROM cms_analytics_session WHERE ip_anonymised != "" AND (geo_resolved IS NULL OR country IS NULL OR country = "" OR country = "Unknown") LIMIT '.$limit);
			foreach ($query->result_array() as $row) {
				if (!$this->_session_needs_geo_resolve($row)) {
					continue;
				}
				$geo = $this->_resolve_geo($row['ip_anonymised']);
				$this->_save_session_geo($row['session_id'], $geo);
				$processed++;
			}
			$this->_write_geo_cache();
		}

		$purge_seconds = (int)$this->_bot_pageview_purge_seconds;
		$this->db->query('DELETE FROM cms_analytics_pageview WHERE bot = 1 AND created < DATE_SUB(NOW(), INTERVAL '.$purge_seconds.' SECOND)');

		return $processed;

	}

	function get_geoip_database_path() {

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('analytics/analytics_settings');
		$filename = trim((string)($settings['geoip_database'] ?? ''));

		if ($filename === '') {
			return '';
		}

		$path = $GLOBALS['config']['upload_path'].$filename;
		if (!is_file($path)) {
			return '';
		}

		return $path;

	}

	function geoip_database_configured() {

		return $this->get_geoip_database_path() !== '';

	}

	function geoip_lookup_available() {

		return $this->geoip_database_configured() && $this->_geoip_reader_available();

	}

	function get_geoip_error_message() {

		if (!$this->geoip_database_configured()) {
			return 'geoip database file is not set, check analytics module settings';
		}

		if (!$this->_geoip_reader_available()) {
			return 'GeoIP PHP library is missing. Run composer install in the project root on the server.';
		}

		return '';

	}

	function get_geoip_debug_report() {

		$lines = [];
		$lines[] = '=== GeoIP diagnostics ===';
		$lines[] = 'generated: '.date('Y-m-d H:i:s');
		$lines[] = '';

		$this->load->model('cms/cms_page_panel_model');
		$settings = $this->cms_page_panel_model->get_cms_page_panel_settings('analytics/analytics_settings');
		$settings_filename = trim((string)($settings['geoip_database'] ?? ''));
		$resolved_path = $settings_filename !== '' ? $GLOBALS['config']['upload_path'].$settings_filename : '';
		$configured_path = $this->get_geoip_database_path();

		$lines[] = '[config]';
		$lines[] = 'upload_path: '.$GLOBALS['config']['upload_path'];
		$lines[] = 'settings filename: '.($settings_filename !== '' ? $settings_filename : '(empty)');
		$lines[] = 'resolved path: '.($resolved_path !== '' ? $resolved_path : '(empty)');
		$lines[] = 'file exists: '.($resolved_path !== '' && is_file($resolved_path) ? 'yes' : 'no');
		$lines[] = 'file size: '.($resolved_path !== '' && is_file($resolved_path) ? (string)filesize($resolved_path) : 'n/a');
		$lines[] = 'file readable: '.($resolved_path !== '' && is_readable($resolved_path) ? 'yes' : 'no');
		$lines[] = 'get_geoip_database_path: '.($configured_path !== '' ? $configured_path : '(empty)');
		$lines[] = 'dashboard geoip_error: '.($this->get_geoip_error_message() !== '' ? $this->get_geoip_error_message() : '(none)');
		$lines[] = '';

		$autoload = $GLOBALS['config']['base_path'].'vendor/autoload.php';
		$reader_available = $this->_geoip_reader_available();

		$lines[] = '[composer]';
		$lines[] = 'autoload exists: '.(is_file($autoload) ? 'yes' : 'no');
		$lines[] = 'GeoIp2 Reader class: '.($reader_available ? 'yes' : 'no');
		$lines[] = 'geoip2/geoip2 lock version: '.$this->_geoip_debug_composer_version();
		$lines[] = '';

		$lines[] = '[environment]';
		$lines[] = 'PHP version: '.PHP_VERSION;
		$lines[] = 'ext-maxminddb loaded: '.(extension_loaded('maxminddb') ? 'yes' : 'no');
		$lines[] = '';

		$reader = null;
		$lines[] = '[reader metadata]';
		if ($configured_path === '' || !$reader_available) {
			$lines[] = 'Reader not opened (missing file or Composer library)';
		} else {
			try {
				$reader = new \GeoIp2\Database\Reader($configured_path);
				$meta = $reader->metadata();
				$lines[] = 'databaseType: '.($meta->databaseType ?? '');
				$lines[] = 'description: '.$this->_geoip_debug_format_metadata_description($meta->description ?? '');
				$lines[] = 'buildEpoch: '.(!empty($meta->buildEpoch) ? date('Y-m-d H:i:s', (int)$meta->buildEpoch) : '');
				$lines[] = 'binaryFormatMajorVersion: '.($meta->binaryFormatMajorVersion ?? '');
				$lines[] = 'binaryFormatMinorVersion: '.($meta->binaryFormatMinorVersion ?? '');
			} catch (Exception $e) {
				$lines[] = 'ERROR opening Reader: '.get_class($e).': '.$e->getMessage();
				$reader = null;
			}
		}
		$lines[] = '';

		$test_ips = ['8.8.8.8', '8.8.8.0'];
		$sample_query = $this->db->query('SELECT DISTINCT ip_anonymised FROM cms_analytics_pageview WHERE ip_anonymised != "" ORDER BY cms_analytics_pageview_id DESC LIMIT 3');
		foreach ($sample_query->result_array() as $row) {
			$ip = trim((string)($row['ip_anonymised'] ?? ''));
			if ($ip !== '' && !in_array($ip, $test_ips, true)) {
				$test_ips[] = $ip;
			}
		}

		$lines[] = '[test lookups]';
		foreach ($test_ips as $ip) {
			$lines[] = $this->_geoip_debug_lookup_line($reader, $ip);
		}
		$lines[] = '';

		$lines[] = '[recent sessions]';
		$sessions = $this->get_last_sessions(10);
		if (empty($sessions)) {
			$lines[] = '(no sessions)';
		} else {
			foreach ($sessions as $session) {
				$lines[] = trim((string)($session['ip_anonymised'] ?? ''))
					.' | '.trim((string)($session['country'] ?? ''))
					.' | started: '.trim((string)($session['started'] ?? ''))
					.' | geo_resolved: '.trim((string)($session['geo_resolved'] ?? ''));
			}
		}

		return implode("\n", $lines);

	}

	function get_hourly_pageviews($hours = 168) {

		$hours = (int)$hours;
		$query = $this->db->query("SELECT DATE_FORMAT(created, '%Y-%m-%d %H:00:00') AS hour_bucket, COUNT(*) AS pageviews FROM cms_analytics_pageview WHERE bot = 0 AND created >= DATE_SUB(NOW(), INTERVAL ".$hours." HOUR) GROUP BY hour_bucket ORDER BY hour_bucket ASC");
		$rows = $query->result_array();

		$counts = array();
		foreach ($rows as $row) {
			$counts[$row['hour_bucket']] = (int)$row['pageviews'];
		}

		$series = array();
		$start = strtotime('-'.($hours - 1).' hours', strtotime(date('Y-m-d H:00:00')));
		for ($i = 0; $i < $hours; $i++) {
			$bucket = date('Y-m-d H:00:00', $start + ($i * 3600));
			$series[] = array(
				'hour' => $bucket,
				'pageviews' => !empty($counts[$bucket]) ? $counts[$bucket] : 0,
			);
		}

		return $series;

	}

	function get_hourly_sessions($hours = 168) {

		$hours = (int)$hours;
		$query = $this->db->query('SELECT DATE_FORMAT(started, "%Y-%m-%d %H:00:00") AS hour_bucket, COUNT(*) AS sessions FROM cms_analytics_session WHERE started >= DATE_SUB(NOW(), INTERVAL '.$hours.' HOUR) GROUP BY hour_bucket ORDER BY hour_bucket ASC');
		$rows = $query->result_array();

		$counts = array();
		foreach ($rows as $row) {
			$counts[$row['hour_bucket']] = (int)$row['sessions'];
		}

		$series = array();
		$start = strtotime('-'.($hours - 1).' hours', strtotime(date('Y-m-d H:00:00')));
		for ($i = 0; $i < $hours; $i++) {
			$bucket = date('Y-m-d H:00:00', $start + ($i * 3600));
			$series[] = array(
				'hour' => $bucket,
				'sessions' => !empty($counts[$bucket]) ? $counts[$bucket] : 0,
			);
		}

		return $series;

	}

	function chart_pageviews_by_hour_png($width = 900, $height = 300) {

		if (!function_exists('imagecreatetruecolor')) {
			return '';
		}

		$width = max(200, min(2000, (int)$width));
		$height = max(80, min(800, (int)$height));

		$pageview_series = $this->get_hourly_pageviews(168);
		$session_series = $this->get_hourly_sessions(168);
		$series = array();

		foreach ($pageview_series as $i => $point) {
			$series[] = array(
				'hour' => $point['hour'],
				'pageviews' => $point['pageviews'],
				'sessions' => !empty($session_series[$i]['sessions']) ? $session_series[$i]['sessions'] : 0,
			);
		}

		$max_pageviews = 1;
		$max_sessions = 1;
		foreach ($series as $point) {
			if ($point['pageviews'] > $max_pageviews) {
				$max_pageviews = $point['pageviews'];
			}
			if ($point['sessions'] > $max_sessions) {
				$max_sessions = $point['sessions'];
			}
		}

		$padding_left = 50;
		$padding_right = 50;
		$padding_top = 24;
		$padding_bottom = 36;
		$plot_w = $width - $padding_left - $padding_right;
		$plot_h = $height - $padding_top - $padding_bottom;

		$img = imagecreatetruecolor($width, $height);
		imagealphablending($img, true);
		$white = imagecolorallocate($img, 255, 255, 255);
		$black = imagecolorallocate($img, 40, 40, 40);
		$grey = imagecolorallocate($img, 200, 200, 200);
		$blue = imagecolorallocate($img, 50, 100, 200);
		$green = imagecolorallocate($img, 30, 110, 60);
		$grid_midnight = imagecolorallocatealpha($img, 40, 40, 40, 63);
		$grid_six_hour = imagecolorallocatealpha($img, 40, 40, 40, 102);
		imagefilledrectangle($img, 0, 0, $width, $height, $white);

		$count = count($series);
		if ($count < 2) {
			imagestring($img, 3, $padding_left, $padding_top, 'No data', $black);
			ob_start();
			imagepng($img);
			$png = ob_get_clean();
			imagedestroy($img);
			return $png;
		}

		$y0 = $padding_top + $plot_h;
		$y_top = $padding_top;

		foreach ($series as $i => $point) {
			$hour_ts = strtotime($point['hour']);
			$hour_of_day = (int)date('G', $hour_ts);
			if ($hour_of_day % 6 !== 0) {
				continue;
			}
			$x = $padding_left + (int)round($plot_w * $i / ($count - 1));
			$grid_color = ($hour_of_day === 0) ? $grid_midnight : $grid_six_hour;
			$this->_chart_dashed_vline($img, $x, $y_top, $y0, $grid_color);
		}

		imageline($img, $padding_left, $y_top, $padding_left, $y0, $black);
		imageline($img, $width - $padding_right, $y_top, $width - $padding_right, $y0, $black);
		imageline($img, $padding_left, $y0, $width - $padding_right, $y0, $black);

		for ($tick = 0; $tick <= 4; $tick++) {
			$val = (int)round($max_pageviews * $tick / 4);
			$y = $y0 - (int)round($plot_h * $tick / 4);
			imageline($img, $padding_left - 4, $y, $padding_left, $y, $grey);
			$label = (string)$val;
			$label_w = imagefontwidth(2) * strlen($label);
			imagestring($img, 2, $padding_left - 8 - $label_w, $y - 6, $label, $blue);
		}

		for ($tick = 0; $tick <= 4; $tick++) {
			$val = (int)round($max_sessions * $tick / 4);
			$y = $y0 - (int)round($plot_h * $tick / 4);
			imageline($img, $width - $padding_right, $y, $width - $padding_right + 4, $y, $grey);
			$label = (string)$val;
			imagestring($img, 2, $width - $padding_right + 8, $y - 6, $label, $green);
		}

		$prev_x = null;
		$prev_y = null;
		foreach ($series as $i => $point) {
			$x = $padding_left + (int)round($plot_w * $i / ($count - 1));
			$y = $y0 - (int)round($plot_h * $point['pageviews'] / $max_pageviews);
			if ($prev_x !== null) {
				imageline($img, $prev_x, $prev_y, $x, $y, $blue);
			}
			$prev_x = $x;
			$prev_y = $y;
		}

		$prev_x = null;
		$prev_y = null;
		foreach ($series as $i => $point) {
			$x = $padding_left + (int)round($plot_w * $i / ($count - 1));
			$y = $y0 - (int)round($plot_h * $point['sessions'] / $max_sessions);
			if ($prev_x !== null) {
				imageline($img, $prev_x, $prev_y, $x, $y, $green);
			}
			$prev_x = $x;
			$prev_y = $y;
		}

		$day_buckets = array();
		foreach ($series as $i => $point) {
			$day = date('Y-m-d', strtotime($point['hour']));
			if (empty($day_buckets[$day])) {
				$day_buckets[$day] = array();
			}
			$day_buckets[$day][] = $i;
		}

		foreach ($day_buckets as $day => $indices) {
			if (count($indices) < 6) {
				continue;
			}
			$mid_index = $indices[(int)floor((count($indices) - 1) / 2)];
			$x = $padding_left + (int)round($plot_w * $mid_index / ($count - 1));
			$label = date('j M', strtotime($day));
			$label_w = imagefontwidth(2) * strlen($label);
			imagestring($img, 2, $x - (int)($label_w / 2), $height - 18, $label, $black);
		}

		imagestring($img, 2, $padding_left, 6, 'Pageviews', $blue);
		imagestring($img, 2, $width - $padding_right - 52, 6, 'Sessions started', $green);
		imagestring($img, 3, (int)(($width - 320) / 2), 6, 'Pageviews & sessions started per hour (last 7 days)', $black);

		ob_start();
		imagepng($img);
		$png = ob_get_clean();
		imagedestroy($img);

		return $png;

	}

	private function _geo_cache_path() {

		return $GLOBALS['config']['base_path'].'cache/analytics_geo_cache.json';

	}

	private function _load_geo_cache() {

		if ($this->_geo_cache !== null) {
			return $this->_geo_cache;
		}

		$path = $this->_geo_cache_path();
		if (!file_exists($path)) {
			$this->_geo_cache = array();
			return $this->_geo_cache;
		}

		$json = file_get_contents($path);
		$data = cms_json_decode($json, 'analytics_geo_cache.json');
		if (!is_array($data)) {
			$data = array();
		}

		$cutoff = strtotime('-30 days');
		foreach ($data as $ip => $entry) {
			if (empty($entry['resolved']) || strtotime($entry['resolved']) < $cutoff) {
				unset($data[$ip]);
				$this->_geo_cache_dirty = true;
			}
		}

		$this->_geo_cache = $data;
		return $this->_geo_cache;

	}

	private function _write_geo_cache() {

		if (!$this->_geo_cache_dirty || $this->_geo_cache === null) {
			return;
		}

		file_put_contents($this->_geo_cache_path(), json_encode($this->_geo_cache, JSON_PRETTY_PRINT));
		$this->_geo_cache_dirty = false;

	}

	private function _php_pageview_table_ready() {

		static $ready = null;

		if ($ready !== null) {
			return $ready;
		}

		$query = $this->db->query("SHOW TABLES LIKE 'cms_analytics_pageview_php'");
		$ready = $query->num_rows() > 0;

		return $ready;

	}

	private function _process_php_pageviews_batch($limit) {

		if (!$this->_php_pageview_table_ready()) {
			return 0;
		}

		$limit = (int)$limit;
		$processed = 0;
		$grace = (int)$this->_php_pageview_grace_seconds;
		$match_window = (int)$this->_php_pageview_match_seconds;

		$query = $this->db->query('SELECT * FROM cms_analytics_pageview_php WHERE created < DATE_SUB(NOW(), INTERVAL '.$grace.' SECOND) ORDER BY created ASC LIMIT '.$limit);
		$rows = $query->result_array();

		foreach ($rows as $row) {
			$match = $this->db->query('SELECT cms_analytics_pageview_id FROM cms_analytics_pageview WHERE beacon_id = ? AND page = ? AND ABS(TIMESTAMPDIFF(SECOND, created, ?)) <= ? LIMIT 1',
					array($row['beacon_id'], $row['page'], $row['created'], $match_window))->row_array();

			if (!empty($match['cms_analytics_pageview_id'])) {
				$this->db->query('DELETE FROM cms_analytics_pageview_php WHERE cms_analytics_pageview_php_id = ? LIMIT 1',
						array((int)$row['cms_analytics_pageview_php_id']));
				$processed++;
				continue;
			}

			$this->db->query('INSERT INTO cms_analytics_pageview (pageview_token, session_id, beacon_id, language, page, ip_anonymised, user_agent, viewport_w, viewport_h, seconds, scroll_pct, bot, created, updated) VALUES (?, "", ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, ?, ?)',
					array(
						$row['pageview_token'],
						$row['beacon_id'],
						$row['language'] ?? '',
						$row['page'],
						$row['ip_anonymised'] ?? '',
						$row['user_agent'] ?? '',
						$row['created'],
						$row['created'],
					));

			$this->db->query('DELETE FROM cms_analytics_pageview_php WHERE cms_analytics_pageview_php_id = ? LIMIT 1',
					array((int)$row['cms_analytics_pageview_php_id']));
			$processed++;
		}

		return $processed;

	}

	private function _aggregate_pageviews_for_visitor($visitor_id) {

		$sql = 'SELECT MIN(created) AS started, MAX(updated) AS last_activity, COUNT(*) AS pageviews, COALESCE(SUM(seconds), 0) AS total_seconds, '
				.'(SELECT language FROM cms_analytics_pageview p2 WHERE (p2.beacon_id = ? OR p2.session_id = ?) AND p2.bot = 0 ORDER BY p2.updated DESC LIMIT 1) AS language, '
				.'(SELECT page FROM cms_analytics_pageview p2 WHERE (p2.beacon_id = ? OR p2.session_id = ?) AND p2.bot = 0 ORDER BY p2.created ASC LIMIT 1) AS first_page, '
				.'(SELECT page FROM cms_analytics_pageview p2 WHERE (p2.beacon_id = ? OR p2.session_id = ?) AND p2.bot = 0 ORDER BY p2.updated DESC LIMIT 1) AS last_page, '
				.'(SELECT ip_anonymised FROM cms_analytics_pageview p2 WHERE (p2.beacon_id = ? OR p2.session_id = ?) AND p2.bot = 0 ORDER BY p2.created ASC LIMIT 1) AS ip_anonymised, '
				.'(SELECT user_agent FROM cms_analytics_pageview p2 WHERE (p2.beacon_id = ? OR p2.session_id = ?) AND p2.bot = 0 ORDER BY p2.created ASC LIMIT 1) AS user_agent '
				.'FROM cms_analytics_pageview WHERE (beacon_id = ? OR session_id = ?) AND bot = 0';

		$row = $this->db->query($sql, array(
				$visitor_id, $visitor_id,
				$visitor_id, $visitor_id,
				$visitor_id, $visitor_id,
				$visitor_id, $visitor_id,
				$visitor_id, $visitor_id,
				$visitor_id, $visitor_id,
		))->row_array();

		if (empty($row['started'])) {
			return false;
		}

		return $row;

	}

	private function _sync_session($visitor_id, $assign_pageviews) {

		if ($visitor_id === '') {
			return false;
		}

		$aggregate = $this->_aggregate_pageviews_for_visitor($visitor_id);
		if ($aggregate === false) {
			return false;
		}

		$this->db->query('INSERT INTO cms_analytics_session (session_id, started, last_activity, pageviews, total_seconds, language, first_page, last_page, ip_anonymised, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE started = VALUES(started), last_activity = VALUES(last_activity), pageviews = VALUES(pageviews), total_seconds = VALUES(total_seconds), language = VALUES(language), first_page = VALUES(first_page), last_page = VALUES(last_page), ip_anonymised = IF(ip_anonymised = "" OR ip_anonymised IS NULL, VALUES(ip_anonymised), ip_anonymised), user_agent = IF(user_agent = "" OR user_agent IS NULL, VALUES(user_agent), user_agent)',
				array(
					$visitor_id,
					$aggregate['started'],
					$aggregate['last_activity'],
					(int)$aggregate['pageviews'],
					(int)$aggregate['total_seconds'],
					$aggregate['language'] ?? '',
					$aggregate['first_page'] ?? '',
					$aggregate['last_page'] ?? '',
					$aggregate['ip_anonymised'] ?? '',
					$aggregate['user_agent'] ?? '',
				));

		if ($assign_pageviews) {
			$this->db->query('UPDATE cms_analytics_pageview SET session_id = ? WHERE beacon_id = ? AND session_id = "" AND bot = 0',
					array($visitor_id, $visitor_id));
		}

		return true;

	}

	private function _session_needs_geo_resolve($session) {

		if (empty($session['ip_anonymised'])) {
			return false;
		}

		if (analytics_is_local_ip($session['ip_anonymised'])) {
			$expected = analytics_local_geo($session['ip_anonymised']);
			if (($session['country'] ?? '') !== $expected['country']) {
				return true;
			}
			return false;
		}

		if (empty($session['geo_resolved'])) {
			return true;
		}

		return empty($session['country']) || $session['country'] === 'Unknown';

	}

	private function _chart_dashed_vline($img, $x, $y1, $y2, $color, $dash = 4, $gap = 4) {

		$y = $y1;
		while ($y < $y2) {
			$y_end = min($y + $dash, $y2);
			imageline($img, $x, $y, $x, $y_end, $color);
			$y += $dash + $gap;
		}

	}

	private function _resolve_geo($ip_anonymised) {

		$unknown = array('country' => 'Unknown', 'region' => '', 'city' => '');

		if (empty($ip_anonymised)) {
			return $unknown;
		}

		if (analytics_is_local_ip($ip_anonymised)) {
			return analytics_local_geo($ip_anonymised);
		}

		$cache = $this->_load_geo_cache();
		if (!empty($cache[$ip_anonymised])) {
			return array(
				'country' => $cache[$ip_anonymised]['country'] ?? 'Unknown',
				'region' => $cache[$ip_anonymised]['region'] ?? '',
				'city' => $cache[$ip_anonymised]['city'] ?? '',
			);
		}

		$geo = $this->_lookup_maxmind($ip_anonymised);
		$cache[$ip_anonymised] = array(
			'country' => $geo['country'],
			'region' => $geo['region'],
			'city' => $geo['city'],
			'resolved' => date('Y-m-d H:i:s'),
		);
		$this->_geo_cache = $cache;
		$this->_geo_cache_dirty = true;

		return $geo;

	}

	private function _geoip_reader_available() {

		$autoload = $GLOBALS['config']['base_path'].'vendor/autoload.php';
		if (!file_exists($autoload)) {
			return false;
		}

		require_once($autoload);

		return class_exists('GeoIp2\\Database\\Reader');

	}

	private function _geoip_debug_composer_version() {

		$lock_path = $GLOBALS['config']['base_path'].'composer.lock';
		if (!is_file($lock_path)) {
			return '(composer.lock not found)';
		}

		$json = file_get_contents($lock_path);
		if ($json === false) {
			return '(could not read composer.lock)';
		}

		if (preg_match('/"name":\s*"geoip2\/geoip2"[^}]*"version":\s*"([^"]+)"/s', $json, $match)) {
			return $match[1];
		}

		return '(geoip2/geoip2 not found in lock)';

	}

	private function _geoip_debug_format_metadata_description($description) {

		if (is_array($description)) {
			if (!empty($description['en'])) {
				return (string)$description['en'];
			}
			$first = reset($description);
			if (is_string($first)) {
				return $first;
			}
			return json_encode($description);
		}

		return (string)$description;

	}

	private function _geoip_place_name($record) {

		if ($record === null) {
			return '';
		}

		if (!empty($record->name)) {
			return (string)$record->name;
		}

		if (!empty($record->names['en'])) {
			return (string)$record->names['en'];
		}

		if (!empty($record->names) && is_array($record->names)) {
			foreach ($record->names as $name) {
				if (is_string($name) && $name !== '') {
					return $name;
				}
			}
		}

		if (!empty($record->isoCode)) {
			return (string)$record->isoCode;
		}

		return '';

	}

	private function _geoip_debug_lookup_line($reader, $ip) {

		if ($reader === null) {
			return $ip.' => ERROR: Reader not available';
		}

		try {
			$record = $reader->city($ip);
			$country = $this->_geoip_place_name($record->country);
			if ($country === '') {
				$iso = $record->country->isoCode ?? '';
				return $ip.' => empty country name'.($iso !== '' ? ' (iso: '.$iso.')' : '');
			}

			return $ip.' => '.$country.' / '.$this->_geoip_place_name($record->mostSpecificSubdivision).' / '.$this->_geoip_place_name($record->city);

		} catch (\GeoIp2\Exception\AddressNotFoundException $e) {

			return $ip.' => ERROR AddressNotFoundException: '.$e->getMessage();

		} catch (Exception $e) {

			return $ip.' => ERROR '.get_class($e).': '.$e->getMessage();

		}

	}

	private function _lookup_maxmind($ip_anonymised) {

		$unknown = array('country' => 'Unknown', 'region' => '', 'city' => '');

		$mmdb = $this->get_geoip_database_path();
		if ($mmdb === '' || !$this->_geoip_reader_available()) {
			return $unknown;
		}

		try {
			$reader = new \GeoIp2\Database\Reader($mmdb);
			$record = $reader->city($ip_anonymised);
			$country = $this->_geoip_place_name($record->country);
			if ($country === '') {
				return $unknown;
			}
			return array(
				'country' => $country,
				'region' => $this->_geoip_place_name($record->mostSpecificSubdivision),
				'city' => $this->_geoip_place_name($record->city),
			);
		} catch (\GeoIp2\Exception\AddressNotFoundException $e) {
			return $unknown;
		} catch (Exception $e) {
			return $unknown;
		}

	}

	private function _save_session_geo($session_id, $geo) {

		$this->db->query('UPDATE cms_analytics_session SET country = ?, region = ?, city = ?, geo_resolved = NOW() WHERE session_id = ? LIMIT 1',
				array($geo['country'], $geo['region'], $geo['city'], $session_id));

	}

}