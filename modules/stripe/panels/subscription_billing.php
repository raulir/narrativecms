<?php

namespace stripe;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides service subscription_billing (CMS provider_pattern.md): what the subscription domain asks of the
 * Stripe side of a subscription. Called only by subscription_model::billing_call() (run_action with
 * _provider_call, a key no request can carry), never from a browser.
 *
 * cms_do: sync, set_auto_renew, stop_renewal_by_id, payment_method, change_price, checkout_links, absolute_url.
 */
class subscription_billing extends \Controller {

	function panel_action($params){

		if (empty($params['_provider_call'])){
			return ['ok' => 0, 'error' => 'Not available'];
		}

		$do = (string)($params['cms_do'] ?? '');
		$user_id = (int)($params['user_id'] ?? 0);

		$this->load->model('stripe/stripe_model');
		$this->load->model('stripe/stripe_subscription_model');
		$subs = $this->stripe_subscription_model;

		if ($do === 'sync'){
			return $subs->sync_user_subscription($user_id);
		}
		if ($do === 'set_auto_renew'){
			return $subs->set_auto_renew_for_user($user_id, !empty($params['auto_renew']));
		}
		if ($do === 'stop_renewal_by_id'){
			return $subs->stop_renewal_by_id((string)($params['subscription_id'] ?? ''));
		}
		if ($do === 'payment_method'){
			return $subs->create_payment_method_update_session($user_id, (string)($params['return_url'] ?? ''));
		}
		if ($do === 'change_price'){
			return $subs->change_subscription_price_for_user($user_id, (string)($params['stripe_price_id'] ?? ''));
		}
		if ($do === 'checkout_links'){
			$links = $this->stripe_model->get_checkout_link_urls();
			return array_merge(['ok' => 1], is_array($links) ? $links : []);
		}
		if ($do === 'absolute_url'){
			return ['ok' => 1, 'url' => $this->stripe_model->absolute_url((string)($params['url'] ?? ''))];
		}

		return ['ok' => 0, 'error' => 'Unknown billing action'];

	}

}
