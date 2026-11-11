<?php

namespace cms;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Builds a missing cache/<module>__<file>.css on request. The web server answers 404 for a
 * missing cache css; a client that needs the file (the app worker) asks here, then fetches it again.
 * Public: it only builds a module css that has a source, as any page render would.
 */
class cms_panel_css extends \Controller {

	function panel_action($params = []){

		if (($params['cms_do'] ?? '') !== 'rebuild'){
			return ['ok' => 0];
		}

		$this->load->model('cms/cms_css_model');

		return ['ok' => $this->cms_css_model->rebuild_cache_css($params['name'] ?? '') ? 1 : 0];

	}

}
