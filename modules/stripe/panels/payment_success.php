<?php use Stripe\ApiOperations\Update;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once($GLOBALS['config']['base_path'] . 'vendor/stripe/init.php');
require_once($GLOBALS['config']['base_path'] . 'vendor/autoload.php');

class payment_success extends CI_Controller{
	
	function panel_params($params){
		
		\Stripe\Stripe::setApiKey($GLOBALS['config']['stripe_secret']);
		
		$this->load->model('cms/cms_page_panel_model');
		$this->load->model('dashboard/user_model');
		$this->load->model('dashboard/samba_model');
		
		// url
		// https://cloudpresenter.localhost/cp/payment-success/?payment_intent=pi_3MdigQDKFjlh9P4l2JBwXK4B&
		//     payment_intent_client_secret=pi_3MdigQDKFjlh9P4l2JBwXK4B_secret_udRKjOGJ74Gn90G7av255gYGc&redirect_status=succeeded
		
		
		// get 
		
		$payment_intent_id = $_GET['payment_intent'];
		
		$payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
		
		if ($payment_intent->status == 'succeeded'){
			
			$filename = $GLOBALS['config']['base_path'].'cache/'.$payment_intent->id.'.json';
			
			$order = json_decode(file_get_contents($filename), true);
			
			$user = $this->user_model->get_user($order['cp_user_id']);
			
			$plan = $this->cms_page_panel_model->get_cms_page_panel($order['plan_id']);
			
			
//			$samba_user = $this->samba_model->get_samba_user_by_email($user['email']);
			
//			_print_r($plan);
			
//			$user['samba_user_id']
//			$plan['samba_plan_id']

			$this->samba_model->update_samba_user_plan($user['samba_user_id'], $plan['samba_plan_id'], $order['plan_licences'] - 1);
			
			// update cp user
			
			$time = time();
			
			$this->user_model->update_user($user['cp_user_id'], [
					'samba_user_id' => $user['samba_user_id'], 
					'plan_id' => $plan['cms_page_panel_id'],
					'plan_licences' => $order['plan_licences'],
					'plan_period' => $order['plan_period'],
					'plan_end_time' => strtotime(('+1 '.($order['plan_period'] == 'm' ? 'month' : 'year')), $time),
			]);
			
			// subscribe to list
			$list_id = '74b27cb6-4f9d-49d1-bf93-c9a47e06a485';
			
			$sendgrid = new \SendGrid($GLOBALS['config']['sendgrid_apikey']);
			$request_body = json_decode('{
            	"list_ids": ["'.$list_id.'"],
            	"contacts": [
                	{
                    	"email": "'.$user['email'].'",
                    	"first_name": "'.$user['first_name'].'",
                    	"last_name": "'.$user['last_name'].'"
					}
            	]
        	}');
			
			$response = $sendgrid->client->marketing()->contacts()->put($request_body);
				
			
//			_print_r($order);
			
		}
		
		
// _print_r($payment_intent);
		
	}
	
}
