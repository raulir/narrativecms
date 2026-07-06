<?php defined('BASEPATH') OR exit('No direct script access allowed');

class analytics_dashboard extends CI_Controller {

	function panel_action($params) {

		$this->load->model('analytics/analytics_model');

		if (!empty($params['do']) && $params['do'] === 'delete_visitor') {
			$deleted = $this->analytics_model->delete_visitor_from_dashboard(
					$params['row_type'] ?? '',
					$params['row_id'] ?? ''
			);

			return array(
				'deleted' => $deleted ? 1 : 0,
				'no_html' => 1,
			);
		}

		$this->analytics_model->process_analytics_batch(500);

		return $params;

	}

	function panel_params($params) {

		$this->load->helper('analytics/analytics_api_helper');
		$this->load->model('analytics/analytics_model');

		$params['total_pageviews'] = $this->analytics_model->get_total_pageviews();
		$params['total_sessions'] = $this->analytics_model->get_total_sessions();
		$params['pageviews_7_days'] = $this->analytics_model->get_pageviews_last_7_days();
		$params['sessions_7_days'] = $this->analytics_model->get_sessions_last_7_days();
		$params['pageviews_30_days'] = $this->analytics_model->get_pageviews_last_30_days();
		$params['sessions_30_days'] = $this->analytics_model->get_sessions_last_30_days();

		$params['last_pageviews'] = $this->analytics_model->get_last_pageviews(50);
		$params['last_sessions'] = $this->analytics_model->get_last_sessions(50);
		$params['has_multiple_languages'] = $this->analytics_model->site_has_multiple_languages();

		$geoip_error = $this->analytics_model->get_geoip_error_message();
		if ($geoip_error !== '') {
			$params['geoip_error'] = $geoip_error;
			$params['geo_top'] = [];
		} else {
			$params['geo_top'] = $this->analytics_model->get_geo_top(50);
		}

		$params['top_pages'] = $this->analytics_model->get_top_pages(20);

		$params['chart_url'] = $GLOBALS['config']['base_url'].'admin/analytics/chart/?t='.time();

		$params['show_geoip_debug'] = $this->analytics_model->get_show_geoip_debug();
		if ($params['show_geoip_debug']) {
			$params['geoip_debug_report'] = $this->analytics_model->get_geoip_debug_report();
		}

		add_css('modules/analytics/css/analytics_dashboard.scss');
		add_css('modules/cms/css/cms_popup_yes_no.scss');
		$GLOBALS['_panel_js'][] = 'modules/analytics/js/analytics_dashboard.js';

		return $params;

	}

}