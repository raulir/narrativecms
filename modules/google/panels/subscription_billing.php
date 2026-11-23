<?php

namespace google;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Provides service subscription_billing (CMS provider_pattern.md): what the subscription domain asks of the
 * Google Play side of a subscription. Called only by subscription_model::billing_call() (run_action with
 * _provider_call, a key no request can carry), never from a browser.
 *
 * cms_do: bills_user, manage_url, sync, cancel_renewal, stop_renewal, set_provider, stripe_sale.
 */
class subscription_billing extends \Controller {

	function panel_action($params){

		if (empty($params['_provider_call'])){
			return ['ok' => 0, 'error' => 'Not available'];
		}

		$do = (string)($params['cms_do'] ?? '');
		$user_id = (int)($params['user_id'] ?? 0);

		$this->load->model('google/google_model');
		$play = $this->google_model;

		if ($do === 'bills_user'){
			// The user's subscription is the Play one
			return ['ok' => 1, 'yes' => $play->provider_for_user($user_id) === 'google' ? 1 : 0];
		}
		if ($do === 'manage_url'){
			return ['ok' => 1, 'url' => $play->subscription_manage_url($play->play_product_id_for_user($user_id))];
		}
		if ($do === 'sync'){
			return $play->sync_user($user_id);
		}
		if ($do === 'cancel_renewal'){
			return $play->cancel_renewal_for_user($user_id);
		}
		if ($do === 'stop_renewal'){
			// Account deletion: only a user with a Play purchase holds one (held)
			$this->load->model('user/user_model');
			$meta = $this->user_model->get_user_meta_value_for_id($user_id, $play::META_PLAY, []);
			$token = is_array($meta) ? trim((string)($meta['purchase_token'] ?? '')) : '';
			if ($token === ''){
				return ['ok' => 1, 'held' => 0];
			}
			return array_merge($play->cancel_renewal_for_user($user_id), ['held' => 1]);
		}
		if ($do === 'set_provider'){
			$play->set_provider($user_id, (string)($params['provider'] ?? ''));
			return ['ok' => 1];
		}
		if ($do === 'stripe_sale'){
			// A Stripe sale reported to Play (external offers); a failure never stops the sale
			try {
				$play->report_stripe_sale($user_id, is_array($params['facts'] ?? null) ? $params['facts'] : []);
			} catch (\Exception $e) {
				error_log('google: report failed for user '.$user_id);
			}
			return ['ok' => 1];
		}

		return ['ok' => 0, 'error' => 'Unknown billing action'];

	}

}
