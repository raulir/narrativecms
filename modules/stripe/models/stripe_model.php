<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once($GLOBALS['config']['base_path'] . 'vendor/stripe/init.php');

class stripe_model extends CI_Model {
	
	function __construct(){
		
		\Stripe\Stripe::setApiKey($GLOBALS['config']['stripe_secret']);
	
	}

	function get_user_subscription($cp_user_id){
		
		$this->load->model('dashboard/user_model');
		
		$user = $this->user_model->get_current();
		
		
		// get stripe_customer_id
		
		// get subscription
		$subscriptions = \Stripe\Subscription::all(['customer' => $user['stripe_customer_id']]);
		
_print_r($subscriptions);
		
		// if subscription doesn't exist, create
		
		// return subscription
		
/*		
		public function createSubscription($customerId, $planDataItem)
		{
			$subscription = $this->stripe->subscriptions->create([
					'customer' => $customerId,
					'items' => [
							['price' => $planDataItem->price_id],
					],
					'payment_behavior' => 'default_incomplete',
					'expand' => ['latest_invoice.payment_intent'],
					'automatic_tax' => ['enabled' => true],
					// 'proration_behavior' => 'always_invoice',
			]);
			$this->stripe->paymentIntents->update(
					$subscription->latest_invoice->payment_intent->id,
					[
							'setup_future_usage' => "on_session",
					]
					);
		
			return $subscription;
		}
*/		
		
		
	}
	
}
