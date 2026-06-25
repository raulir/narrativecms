<?php defined('BASEPATH') OR exit('No direct script access allowed');

class analytics_geo_resolve extends CI_Controller {

	function panel_action($params) {

		$this->load->model('analytics/analytics_model');
		return $this->analytics_model->resolve_unresolved_batch(500);

	}

}