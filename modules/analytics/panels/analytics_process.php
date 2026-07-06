<?php defined('BASEPATH') OR exit('No direct script access allowed');

class analytics_process extends CI_Controller {

	function panel_action($params) {

		$this->load->model('analytics/analytics_model');
		return $this->analytics_model->process_analytics_batch(500);

	}

}