<?php defined('BASEPATH') OR exit('No direct script access allowed');

class analytics_model extends Model {

	private $_geo_cache = null;
	private $_geo_cache_dirty = false;

	function get_total_pageviews() {

		return (int)$this->db->count_all('cms_analytics_pageview');

	}

	function get_pageviews_last_30_days() {

		$row = $this->db->query("SELECT COUNT(*) AS cnt FROM cms_analytics_pageview WHERE created >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->row_array();
		return !empty($row['cnt']) ? (int)$row['cnt'] : 0;

	}

	function get_last_pageviews($limit = 50) {

		$limit = (int)$limit;
		$query = $this->db->query('SELECT * FROM cms_analytics_pageview ORDER BY created DESC LIMIT '.$limit);
		return $query->result_array();

	}

	function get_top_pages($limit = 20) {

		$limit = (int)$limit;
		$query = $this->db->query('SELECT page, COUNT(*) AS pageviews, ROUND(AVG(seconds)) AS avg_seconds, ROUND(AVG(scroll_pct)) AS avg_scroll FROM cms_analytics_pageview GROUP BY page ORDER BY pageviews DESC LIMIT '.$limit);
		return $query->result_array();

	}

	function get_geo_top($limit = 50) {

		$limit = (int)$limit;
		$query = $this->db->query("SELECT country, region, city, COUNT(*) AS pageviews FROM cms_analytics_pageview WHERE country IS NOT NULL AND country != '' GROUP BY country, region, city ORDER BY pageviews DESC LIMIT ".$limit);
		return $query->result_array();

	}

	function resolve_pageviews_geo($pageviews) {

		foreach ($pageviews as $key => $pageview) {
			if (!$this->_pageview_needs_geo_resolve($pageview)) {
				continue;
			}
			$geo = $this->_resolve_geo($pageview['ip_anonymised']);
			$this->_save_pageview_geo($pageview['cms_analytics_pageview_id'], $geo);
			$pageviews[$key]['country'] = $geo['country'];
			$pageviews[$key]['region'] = $geo['region'];
			$pageviews[$key]['city'] = $geo['city'];
			$pageviews[$key]['geo_resolved'] = date('Y-m-d H:i:s');
		}

		$this->_write_geo_cache();

		return $pageviews;

	}

	function resolve_unresolved_batch($limit = 500) {

		$limit = (int)$limit;
		$query = $this->db->query('SELECT cms_analytics_pageview_id, ip_anonymised FROM cms_analytics_pageview WHERE ip_anonymised != "" AND (geo_resolved IS NULL OR country IS NULL OR country = "" OR country = "Unknown" OR country = "Local") GROUP BY ip_anonymised LIMIT '.$limit);
		$rows = $query->result_array();

		foreach ($rows as $row) {
			$geo = $this->_resolve_geo($row['ip_anonymised']);
			$this->db->query('UPDATE cms_analytics_pageview SET country = ?, region = ?, city = ?, geo_resolved = NOW() WHERE ip_anonymised = ? AND (geo_resolved IS NULL OR country IS NULL OR country = "" OR country = "Unknown" OR country = "Local")',
					array($geo['country'], $geo['region'], $geo['city'], $row['ip_anonymised']));
		}

		$this->_write_geo_cache();

		return count($rows);

	}

	function get_hourly_pageviews($hours = 168) {

		$hours = (int)$hours;
		$query = $this->db->query("SELECT DATE_FORMAT(created, '%Y-%m-%d %H:00:00') AS hour_bucket, COUNT(*) AS pageviews FROM cms_analytics_pageview WHERE created >= DATE_SUB(NOW(), INTERVAL ".$hours." HOUR) GROUP BY hour_bucket ORDER BY hour_bucket ASC");
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

	function chart_pageviews_by_hour_png($width = 900, $height = 300) {

		if (!function_exists('imagecreatetruecolor')) {
			return '';
		}

		$width = max(200, min(2000, (int)$width));
		$height = max(80, min(800, (int)$height));

		$series = $this->get_hourly_pageviews(168);
		$max_pageviews = 0;
		foreach ($series as $point) {
			if ($point['pageviews'] > $max_pageviews) {
				$max_pageviews = $point['pageviews'];
			}
		}
		if ($max_pageviews < 1) {
			$max_pageviews = 1;
		}

		$padding_left = 50;
		$padding_right = 20;
		$padding_top = 20;
		$padding_bottom = 40;
		$plot_w = $width - $padding_left - $padding_right;
		$plot_h = $height - $padding_top - $padding_bottom;

		$img = imagecreatetruecolor($width, $height);
		$white = imagecolorallocate($img, 255, 255, 255);
		$black = imagecolorallocate($img, 40, 40, 40);
		$grey = imagecolorallocate($img, 200, 200, 200);
		$blue = imagecolorallocate($img, 50, 100, 200);
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
		imageline($img, $padding_left, $padding_top, $padding_left, $y0, $black);
		imageline($img, $padding_left, $y0, $width - $padding_right, $y0, $black);

		for ($tick = 0; $tick <= 4; $tick++) {
			$val = (int)round($max_pageviews * $tick / 4);
			$y = $y0 - (int)round($plot_h * $tick / 4);
			imageline($img, $padding_left - 4, $y, $padding_left, $y, $grey);
			imagestring($img, 2, 4, $y - 6, (string)$val, $black);
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

		imagestring($img, 2, $padding_left, $height - 18, date('Y-m-d H:i', strtotime($series[0]['hour'])), $black);
		imagestring($img, 2, $width - $padding_right - 110, $height - 18, date('Y-m-d H:i', strtotime($series[$count - 1]['hour'])), $black);
		imagestring($img, 3, $padding_left, 4, 'Pageviews per hour (last 7 days)', $black);

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

	private function _pageview_needs_geo_resolve($pageview) {

		if (empty($pageview['ip_anonymised'])) {
			return false;
		}

		if (analytics_is_local_ip($pageview['ip_anonymised'])) {
			$expected = analytics_local_geo($pageview['ip_anonymised']);
			if (($pageview['country'] ?? '') !== $expected['country']) {
				return true;
			}
			if (($pageview['region'] ?? '') !== $expected['region']) {
				return true;
			}
			if (($pageview['city'] ?? '') !== $expected['city']) {
				return true;
			}
			return false;
		}

		if (empty($pageview['geo_resolved'])) {
			return true;
		}

		return empty($pageview['country']) || $pageview['country'] === 'Unknown';

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

	private function _lookup_maxmind($ip_anonymised) {

		$unknown = array('country' => 'Unknown', 'region' => '', 'city' => '');

		$mmdb = $GLOBALS['config']['base_path'].'modules/analytics/data/GeoLite2-City.mmdb';
		if (!file_exists($mmdb)) {
			return $unknown;
		}

		$autoload = $GLOBALS['config']['base_path'].'vendor/autoload.php';
		if (!file_exists($autoload)) {
			return $unknown;
		}

		require_once($autoload);

		try {
			$reader = new GeoIp2\Database\Reader($mmdb);
			$record = $reader->city($ip_anonymised);
			$country = $record->country->name ?? '';
			if ($country === '') {
				return $unknown;
			}
			return array(
				'country' => $country,
				'region' => $record->mostSpecificSubdivision->name ?? '',
				'city' => $record->city->name ?? '',
			);
		} catch (\GeoIp2\Exception\AddressNotFoundException $e) {
			return $unknown;
		} catch (Exception $e) {
			return $unknown;
		}

	}

	private function _save_pageview_geo($pageview_id, $geo) {

		$this->db->query('UPDATE cms_analytics_pageview SET country = ?, region = ?, city = ?, geo_resolved = NOW() WHERE cms_analytics_pageview_id = ? LIMIT 1',
				array($geo['country'], $geo['region'], $geo['city'], (int)$pageview_id));

	}

}