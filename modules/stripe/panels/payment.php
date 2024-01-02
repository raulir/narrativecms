<?php use Stripe\ApiOperations\Update;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once($GLOBALS['config']['base_path'] . 'vendor/stripe/init.php');

if (!function_exists('get_user_ip')){

	function get_user_ip(){

		if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			$_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
			$_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
		}

		$client = $_SERVER['HTTP_CLIENT_IP'] ?? false;

		$forward = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? false;

		$remote  = $_SERVER['REMOTE_ADDR'];

		if(filter_var($client, FILTER_VALIDATE_IP)) {
			$ip = $client;
		} elseif(filter_var($forward, FILTER_VALIDATE_IP)) {
			$ip = $forward;
		} else {
			$ip = $remote;
		}

		return $ip;

	}

}

class payment extends CI_Controller{
	
	function panel_params($params){

		// get user extra payment
		\Stripe\Stripe::setApiKey($GLOBALS['config']['stripe_secret']);
		
//		$this->load->model('dashboard/user_model');
//		$this->load->model('dashboard/samba_model');
		$this->load->model('cms/cms_page_panel_model');
		
		
		$params['timestamp'] = time();
		
		
		/*
		
		if (!empty($params['do']) && $params['do'] == 'check'){
	
			$subscription = \Stripe\Subscription::retrieve($params['stripe_subscription_id']);
				
			//			$invoice = \Stripe\Invoice::retrieve($subscription->latest_invoice);
				
			if (empty($subscription->pending_update)){
	
				print(json_encode(['update_success' => 1], JSON_PRETTY_PRINT));
				die;
	
			}
				
			print(json_encode(['update_pending' => 1], JSON_PRETTY_PRINT));
			die;
				
		}
	
		// $user = $this->user_model->get_current();
		$user = $this->user_model->get_user($params['cp_user_id']);
		$time = time();
		
		if (!empty($params['do']) && $params['do'] == 'payment'){

			// if new user, create subscription
			if (empty($params['stripe_subscription_id'])){
				
				$subscription_params = [
						'items' => [
								[
										'price' => $params['stripe_price_id'],
										'quantity' => $params['plan_licences'],
								],
						],
						'currency' => strtolower($user['currency']),
						'payment_behavior' => 'default_incomplete', // not sure about those three
						'expand' => ['latest_invoice.payment_intent'], //
						'automatic_tax' => ['enabled' => false, ], //
						'customer' => $user['stripe_customer_id'],
						'proration_behavior' => 'always_invoice',
				];
				
				if (strtolower($user['currency']) == 'gbp'){
					
					$subscription_params['automatic_tax']['enabled'] = true;
					
					// update customer address
					$ip = get_user_ip();
					
					if ($GLOBALS['config']['environment'] == 'DEV'){
						$ip = '5.152.197.179'; // us tx 12.87.118.0   // london 5.152.197.179 // madrid 195.12.50.155
					}
					
					try {
						$stripe_result = \Stripe\Customer::update($user['stripe_customer_id'] ,[
								'tax' => [
										'ip_address' => $ip,
								],
								'address' => [
										'country' => 'GB',
								]
						]);
					} catch (Exception $e) {
						print(json_encode(['message' => $e->getMessage()], JSON_PRETTY_PRINT));
						die;
					}
					
				}

				try {
						
					$subscription = \Stripe\Subscription::create($subscription_params);
				
										
				} catch (Exception $e) {
		
					print(json_encode(['message' => $e->getMessage()], JSON_PRETTY_PRINT));
					die;
						
				}
				
				$params['stripe_subscription_id'] = $subscription->id;
				
// _print_r($subscription);			
			} else {
				
				$subscription = \Stripe\Subscription::retrieve($params['stripe_subscription_id']);
				
				try {
						
					\Stripe\Subscription::update($params['stripe_subscription_id'], [
							'items' => [
									[
											'id' => $subscription->items->data[0]->id,
											'price' => $params['stripe_price_id'],
											'quantity' => $params['plan_licences'],
									],
							],
							'proration_date' => $params['timestamp'],
							'proration_behavior' => 'always_invoice',
							'payment_behavior' => 'pending_if_incomplete',
					]);
						
				} catch (Exception $e) {
		
					print(json_encode(['message' => $e->getMessage()], JSON_PRETTY_PRINT));
					die;
						
				}
			
			}
				
			// try up to 30s if payment moves to next state
			for ($i = 0; $i <= 5; $i++){
	
				sleep(1);

				$subscription = \Stripe\Subscription::retrieve($params['stripe_subscription_id']);

				$invoice = \Stripe\Invoice::retrieve($subscription->latest_invoice);

				// if payment was miraculously successful - user had prepayment
				if (empty($invoice->payment_intent)){
					
					// update locally and samba
					$this->user_model->update_user($user['cp_user_id'], [
							'plan_id' => $params['plan_id'],
							'plan_licences' => $params['plan_licences'],
							'plan_period' => $params['plan_period'],
							'plan_end_time' => strtotime(('+1 '.($params['plan_period'] == 'm' ? 'month' : 'year')), $time),
					]);
					$plan = $this->cms_page_panel_model->get_cms_page_panel($params['plan_id']);
					$this->samba_model->update_samba_user_plan($user['samba_user_id'], $plan['samba_plan_id'], $params['plan_licences'] - 1);
					
					print(json_encode(['update_success' => 1], JSON_PRETTY_PRINT));
					die;
				}
	
				$payment_intent = \Stripe\PaymentIntent::retrieve($invoice->payment_intent);

// _print_r($invoice);
// _print_r($payment_intent);

				// save payment intent, plan_id and plan_licences
				
				$filename = $GLOBALS['config']['base_path'].'cache/'.$payment_intent->id.'.json';
				file_put_contents($filename, json_encode([
						'plan_id' => $params['plan_id'],
						'plan_licences' => $params['plan_licences'],
						'plan_period' => $params['plan_period'],
						'cp_user_id' => $params['cp_user_id'], // $_SESSION['cp_user_id'],
						'pi' => $payment_intent,
				], JSON_PRETTY_PRINT));
				
				$payment_intent = \Stripe\PaymentIntent::update($payment_intent->id, [
						'receipt_email' => $user['email'],
				]);
				
				if ($payment_intent->status == 'requires_confirmation'){
					
//					$stripe_client = new \Stripe\StripeClient($GLOBALS['config']['stripe_secret']);
					
					$payment_intent = $payment_intent->confirm(
							[
									'payment_method' => $payment_intent->payment_method,
							]
					);
				
					$filename = $GLOBALS['config']['base_path'].'cache/'.$payment_intent->id.'_2.json';
					file_put_contents($filename, json_encode([
							'plan_id' => $params['plan_id'],
							'plan_licences' => $params['plan_licences'],
							'plan_period' => $params['plan_period'],
							'cp_user_id' => $params['cp_user_id'], // $_SESSION['cp_user_id'],
							'pi' => $payment_intent,
					], JSON_PRETTY_PRINT));
					
				}

				if ($payment_intent->status == 'requires_payment_method'){
					
					/*
					$payment_intent->update($payment_intent->id, [
						'setup_future_usage' => 'on_session',
					])
					* /
				
					print(json_encode([
							'do' => 'card_form',
							'payment_intent_client_secret' => $payment_intent->client_secret,
							'payment_intent_publishable_secret' => $GLOBALS['config']['stripe_publishable'],
							'return_url' => (in_array($_SERVER['HTTPS'] ?? '', ['on', 1]) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') == 'https' ?
            						'https://' : 'http://').$_SERVER['HTTP_HOST'].'/'.ltrim($GLOBALS['config']['base_url'], '/').'payment-success/',
					], JSON_PRETTY_PRINT));
					die;
					
				}

				if ($payment_intent->status == 'requires_action' && $payment_intent->next_action->type == 'use_stripe_sdk'){
	
					try {
	
						$payment_intent->confirm([
								'return_url' => (in_array($_SERVER['HTTPS'] ?? '', ['on', 1]) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') == 'https' ?
            						'https://' : 'http://').$_SERVER['HTTP_HOST'].'/'.ltrim($GLOBALS['config']['base_url'], '/').'stripe-payment-success/',
						]);
	
					}  catch (Exception $e) {
	
						print(json_encode(['message' => $e->getMessage()], JSON_PRETTY_PRINT));
						die;
							
					}
						
					$payment_intent = \Stripe\PaymentIntent::retrieve($invoice->payment_intent);
	
					print(json_encode([
							'update_3ds' => 1,
							'payment_intent_client_secret' => $payment_intent->client_secret,
							'url' => $payment_intent->next_action->redirect_to_url->url,
					], JSON_PRETTY_PRINT));
					die;
				}
	
				if (empty($subscription->pending_update)){
					
					// update locally and samba
					$this->user_model->update_user($user['cp_user_id'], [
							'plan_id' => $params['plan_id'],
							'plan_licences' => $params['plan_licences'],
							'plan_period' => $params['plan_period'],
							'plan_end_time' => strtotime(('+1 '.($params['plan_period'] == 'm' ? 'month' : 'year')), $time),
					]);
					$plan = $this->cms_page_panel_model->get_cms_page_panel($params['plan_id']);
					$this->samba_model->update_samba_user_plan($user['samba_user_id'], $plan['samba_plan_id'], $params['plan_licences'] - 1);
					
					print(json_encode(['update_success' => 1], JSON_PRETTY_PRINT));
					die;
					
				}
					
				sleep(6);
					
			}
				
			print(json_encode(['update_pending' => 1], JSON_PRETTY_PRINT));
			die;
				
		}
		
		// debug
		$params['params_start'] = $params;

		$user = [];
//		if ($params['cp_user_id'] == $_SESSION['cp_user_id']){
			$user = $this->user_model->get_user($params['cp_user_id']);
//		}
// _print_r($user);

		// display
// _print_r($params);		
		// get stripe_price_id for selected plan and period and user currency
		$plan = $this->cms_page_panel_model->get_cms_page_panel($params['plan_id']);
// _print_r($plan);		
// _print_r($params);
		foreach ($plan['prices'] as $price){
			
			if ($price['currency'] == $user['currency'] && $price['period'] == $params['plan_period']){
				
				if (empty($GLOBALS['config']['environment'])){
					$params['stripe_price_id'] = $price['stripe_price_id'];
				} else {
					$params['stripe_price_id'] = $price['test_stripe_price_id'];
				}

			}
			
		}
		
		try {
			$price = \Stripe\Price::retrieve($params['stripe_price_id']);
		} catch (Exception $e) {
			_html_error('Stripe: Retrieving price failed', 500);
			die();
		}
		
		// check if customer has default payment method
		try {
			$customer = \Stripe\Customer::retrieve($user['stripe_customer_id']);
		} catch (Exception $e) {
			_html_error('Stripe: Retrieving customer failed', 500);
			die();
		}
		
// _print_r($customer);
// die();
		
		if (empty($customer['invoice_settings']['default_payment_method'])){
				
			$payment_methods = \Stripe\Customer::allPaymentMethods($user['stripe_customer_id'], ['type' => 'card']);
			
			if (!empty($payment_methods->data[0])){
				
				\Stripe\Customer::update($user['stripe_customer_id'], ['invoice_settings' => ['default_payment_method' => $payment_methods->data[0]->id]]);
			
			}
	
		}
		
		$subscriptions = \Stripe\Subscription::all(['customer' => $user['stripe_customer_id']]);
		
// _print_r($subscriptions);
		$proration_date = time();
			
		if (empty($subscriptions->data[0])){
// _print_r($preview_params);			
			// if user doesn't have stripe subscription, create
			// $invoice = \Stripe\Invoice::upcoming($preview_params);
//			_print_r('2');
				
			/*
			$subscription_params['customer'] = $user['stripe_customer_id'];
			$subscription = \Stripe\Subscription::create($subscription_params);
			* /
// _print_r($price);			
			// just show numbers
			$params['extra_payment'] = number_format($price->unit_amount_decimal/100 * $params['plan_licences'], 2). ' ' . strtoupper($price->currency);
			$params['plan_payment'] = number_format($price->unit_amount_decimal/100 * $params['plan_licences'], 2) . ' ' . strtoupper($price->currency) . '/' . $price->recurring->interval;
			$params['timestamp'] = $proration_date;
			$params['period_end'] = date('Y-m-d', strtotime(('+1 '.$price->recurring->interval), strtotime(date('Y-m-d', $proration_date))));
			
		} else {
//			_print_r('3');
				
			// if user has subscription
			// preview params
/*			$preview_params = [
					'subscription' => $subscriptions->data[0]->id,
					'subscription_items' => [
	    					[
	    							'price' => $params['stripe_price_id'],
	    							'quantity' => $params['plan_licences'],
	    					],
	  				],
					'currency' => strtolower($user['currency']),
	// 				'payment_behavior' => 'default_incomplete', // not sure about those three
	//				'expand' => ['latest_invoice.payment_intent'], //
					'automatic_tax' => ['enabled' => true], //
					'customer' => $user['stripe_customer_id'],
					'subscription_proration_date' => $proration_date,
					'subscription_start_date' => $proration_date,
					'subscription_proration_behavior' => 'always_invoice',
			];
* /			

			$preview_params = [
					'subscription' => $subscriptions->data[0]->id,
					'subscription_items' => [
							[
									'id' => $subscriptions->data[0]->items->data[0]->id,
									'price' => $params['stripe_price_id'],
									'quantity' => $params['plan_licences'],
							],
					],
					//						'currency' => strtolower($params['user']['currency']),
					'automatic_tax' => ['enabled' => true], //
					'customer' => $user['stripe_customer_id'],
					'subscription_proration_date' => $proration_date,
					//						'subscription_start_date' => $time,
					'subscription_proration_behavior' => 'always_invoice', // 'create_prorations', //'always_invoice',
			];
				
				
			$invoice = \Stripe\Invoice::upcoming($preview_params);
//			_print_r('4');
				
			/*
			$subscription = $subscriptions->data[0];
			$subscription = \Stripe\Subscription::update($subscription->id, $subscription_params);
			* /
			
			$params['extra_payment'] = number_format($invoice['total']/100, 2). ' ' . strtoupper($price->currency);
			$params['period_end'] = date('Y-m-d', $subscriptions->data[0]->current_period_end);
			$params['plan_payment'] = number_format($price->unit_amount_decimal/100 * $params['plan_licences'], 2) . ' ' . strtoupper($price->currency) . '/' . 
					$price->recurring->interval;
			$params['timestamp'] = $proration_date;
			
			$params['stripe_subscription_id'] = $subscriptions->data[0]->id;
				
			
		} 

//		$params['stripe_subscription_id'] = $subscription->id;
	
	
	/*
		$subscription = \Stripe\Subscription::retrieve($params['stripe_subscription_id']);
	
		$items = [
				[
						'id' => $subscription->items->data[0]->id,
						'price' => $params['stripe_price_id'], // new price
				],
		];
	
		$invoice = \Stripe\Invoice::upcoming([
				'customer' => $params['stripe_cus_id'],
				'subscription' => $params['stripe_subscription_id'],
				'subscription_items' => $items,
				'subscription_proration_date' => $proration_date,
				'subscription_proration_behavior' => 'always_invoice',
		]);
	* /
		
//		$params['params'] = $params;
*/	
		return $params;
	
	}
	
	
	
	
	

}
