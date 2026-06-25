<?php defined('BASEPATH') OR exit('No direct script access allowed');

class analytics_dashboard extends CI_Controller {

	function panel_params($params) {

		$this->load->helper('analytics/analytics_api_helper');
		$this->load->model('analytics/analytics_model');

		$params['total_pageviews'] = $this->analytics_model->get_total_pageviews();
		$params['pageviews_30_days'] = $this->analytics_model->get_pageviews_last_30_days();

		$last_pageviews = $this->analytics_model->get_last_pageviews(50);
		$params['last_pageviews'] = $this->analytics_model->resolve_pageviews_geo($last_pageviews);

		$params['top_pages'] = $this->analytics_model->get_top_pages(20);

		$this->analytics_model->resolve_unresolved_batch(500);
		$params['geo_top'] = $this->analytics_model->get_geo_top(50);

		$params['chart_url'] = $GLOBALS['config']['base_url'].'admin/analytics/chart/?t='.time();

		add_css('modules/analytics/css/analytics_dashboard.scss');

		return $params;

	}

}