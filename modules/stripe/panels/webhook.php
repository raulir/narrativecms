<?php

namespace stripe;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Optional admin-only panel stub. Public endpoint is the module API:
 * POST {base}stripe/webhook/  → modules/stripe/api/webhook.php
 */
class webhook extends \Controller {

	function panel_params($params){

		$params['_html'] = 'Stripe webhook — POST to /stripe/webhook/ (module API). Do not place this panel on a page.';
		return $params;

	}

}
