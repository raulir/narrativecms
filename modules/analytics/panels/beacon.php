<?php

namespace analytics;

defined('BASEPATH') OR exit('No direct script access allowed');

class beacon extends \Controller {

	function panel_action($params) {

		$this->load->helper('analytics/analytics_api_helper');

		$tracking = analytics_beacon_tracking_flags($params);
		$params['js_tracking'] = $tracking['js_tracking'] ? 1 : 0;
		$params['php_tracking'] = $tracking['php_tracking'] ? 1 : 0;

		if (analytics_beacon_enabled() && $tracking['php_tracking']) {
			$this->load->model('analytics/analytics_model');
			$page = analytics_normalise_page($GLOBALS['cms_request_uri'] ?? '');
			$beacon_id = $this->analytics_model->record_php_pageview($page);
			if ($beacon_id !== '') {
				$params['beacon_id'] = $beacon_id;
			}
		}

		$params['_no_cache'] = 1;

		return $params;

	}

	function panel_params($params) {

		$this->load->helper('analytics/analytics_api_helper');

		if (!analytics_beacon_enabled()) {
			$params['show'] = 0;
			return $params;
		}

		$tracking = analytics_beacon_tracking_flags($params);
		$params['js_tracking'] = $tracking['js_tracking'] ? 1 : 0;
		$params['php_tracking'] = $tracking['php_tracking'] ? 1 : 0;

		if (!$tracking['js_tracking'] && !$tracking['php_tracking']) {
			$params['show'] = 0;
			return $params;
		}

		$this->load->model('cms/cms_page_panel_model');
		$settings_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => 'analytics/analytics_settings'));
		$settings = !empty($settings_a[0]) ? $settings_a[0] : array();

		$params['delay'] = !empty($settings['delay']) ? (int)$settings['delay'] : 0;
		$params['collect_engagement'] = !isset($settings['collect_engagement']) || !empty($settings['collect_engagement']);

		if (empty($params['beacon_id'])) {
			$params['beacon_id'] = analytics_get_template_beacon_id();
		}

		return $params;

	}

}