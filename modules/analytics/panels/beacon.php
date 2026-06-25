<?php defined('BASEPATH') OR exit('No direct script access allowed');

class beacon extends CI_Controller {

	function panel_params($params) {

		$this->load->helper('analytics/analytics_api_helper');

		if (!analytics_beacon_enabled()) {
			$params['show'] = 0;
			return $params;
		}

		$this->load->model('cms/cms_page_panel_model');
		$settings_a = $this->cms_page_panel_model->get_cms_page_panels_by(array('panel_name' => 'analytics/analytics_settings'));
		$settings = !empty($settings_a[0]) ? $settings_a[0] : array();

		$params['delay'] = !empty($settings['delay']) ? (int)$settings['delay'] : 0;
		$params['collect_engagement'] = !isset($settings['collect_engagement']) || !empty($settings['collect_engagement']);

		return $params;

	}

}