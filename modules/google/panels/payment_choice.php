<?php

namespace google;

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * The Android app's payment choice: Google Play (default) or ScoreTutor (Stripe), for one plan.
 * Texts are the Google Play settings (definitions/google.json); payment_choice.js shows and runs it.
 */
class payment_choice extends \Controller {

	function panel_params($params){

		$this->load->model('google/google_model');
		$this->load->model('cms/cms_panel_model');
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('user/user_model');

		$settings = $this->google_model->get_settings();
		$config = $this->cms_panel_model->get_cms_panel_config('google/google');
		foreach ($this->cms_panel_model->get_fields_defaults($config['settings'] ?? []) as $name => $default){
			if (strpos($name, 'choice_') === 0){
				$value = trim((string)($settings[$name] ?? ''));
				$params[$name] = $value !== '' ? $value : $default;
			}
		}

		$product_id = (int)($params['product_id'] ?? 0);
		$product = $product_id > 0 ? $this->cms_page_panel_model->get_cms_page_panel($product_id) : [];
		$params['plan_title'] = is_array($product) ? trim((string)($product['heading'] ?? '')) : '';
		$params['product_id'] = $product_id;
		$params['currency_id'] = (int)($params['currency_id'] ?? 0);
		$params['play_product_id'] = trim((string)($params['play_product_id'] ?? ''));
		$params['choice_offer'] = ((string)($params['choice_offer'] ?? '0') === '1') ? '1' : '0';
		// Play's purchase carries this id (obfuscated account id); confirm_purchase checks it
		$user = $this->user_model->is_logged_in() ? $this->user_model->get_current() : [];
		$params['user_id'] = (int)($user['cms_page_panel_id'] ?? $user['user_id'] ?? 0);

		return $params;

	}

}
