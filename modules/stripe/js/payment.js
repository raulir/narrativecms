function payment_init(){

	$('.payment_button_pay').on('click.cms', function(){
		
		var $payment = $('.payment_container')
		
		var data = {
			'do': 'payment',
			'stripe_price_id': $payment.data('stripe_price_id'),
			'stripe_subscription_id': $payment.data('stripe_subscription_id'),
			'timestamp': $payment.data('timestamp'),
			'plan_licences': $payment.data('plan_licences'),
			'plan_id': $payment.data('plan_id'),
			'plan_period': $payment.data('plan_period'),
			'cp_user_id': $payment.data('cp_user_id'),
		}

		$('.payment_button_pay').off('click.cms')
		$('.payment_message_active').removeClass('payment_message_active')
		$('.payment_pending_message').addClass('payment_message_active')
		get_ajax_panel('dashboard/payment', data, function(result){

			if (result.update_3ds) {
				
				var iframe = document.createElement('iframe')
  				iframe.src = result.url
  				iframe.width = 600
  				iframe.height = 400
  				$('.payment_popup_content').get(0).appendChild(iframe)
  				$('.payment_popup_container').addClass('payment_popup_active')
  				
  			}
  				
  			if (result['do'] == 'card_form'){
  			
  				$('.payment_popup_container').addClass('payment_popup_active')
  				
  				$('.payment_popup_content').html('<form id="payment-form"><div id="payment-element"></div><button id="submit">Submit</button>' + 
  						'<div id="error-message"></div></form>')
  			
  				// from stripe example
  				
  				const stripe = Stripe(result.payment_intent_publishable_secret);
  			
  				const options = {
  					clientSecret: result.payment_intent_client_secret,
					appearance: {/*...*/},
				}
  			
  				const elements = stripe.elements(options);

				const paymentElement = elements.create('payment');
				paymentElement.mount('#payment-element');	
  			
  				// submit from stripe example
  				const form = document.getElementById('payment-form');

				form.addEventListener('submit', async (event) => {
				  	event.preventDefault();
				
				  	const {error} = await stripe.confirmPayment({
				    	//`Elements` instance that was used to create the Payment Element
				    	elements,
				    	confirmParams: {
				      		return_url: result.return_url,
				    	},
				  	})
				
				  	if (error) {
					    // This point will only be reached if there is an immediate error when
					    // confirming the payment. Show error to your customer (for example, payment
					    // details incomplete)
					    const messageContainer = document.querySelector('#error-message');
					    messageContainer.textContent = error.message;
				  	} else {
					    // Your customer will be redirected to your `return_url`. For some payment
					    // methods like iDEAL, your customer will be redirected to an intermediate
					    // site first to authorize the payment, then redirected to the `return_url`.
				  	}
				})

			} else {
				
				payment_finalize(result)
				
			} 

		})

	})
	
	$('.payment_button_cancel').on('click.cms', function(){
		$('.payment_container').remove()
	})
	
	$('.payment_popup_close').on('click.cms', function(){
		$('.payment_popup_active').removeClass('payment_popup_active')
		$('.payment_message_active').removeClass('payment_message_active')
		$('.payment_failure_message').addClass('payment_message_active')
	})
	
  	window.addEventListener('message', function(ev) {
    	if (ev.data === '3DS-authentication-complete') {
      		on3DSComplete()
    	}
  	}, false)
	
}

function on3DSComplete() {

	var $payment = $('.payment_container')

    $('.payment_popup_active').removeClass('payment_popup_active')
    
    // TODO: add check if payment was successful
    var data = {
			'do': 'check',
			'stripe_subscription_id': $payment.data('stripe_subscription_id'),
	}

	get_ajax_panel('dashboard/payment', data, function(result){

		payment_finalize(result)
			
	})

}

function payment_finalize(result){
	
	var $payment = $('.payment_container')

	if (result.update_success) {
		
		$('.payment_message_active').removeClass('payment_message_active')
		
		// set correct plan?
/*	
		let request = new XMLHttpRequest();
		request.open('GET', '/api/setcp/123456/' + $payment.data('plantype'), true);
		request.onload = () => {console.log(request.responseText)}
		request.send();
*/		
		$('.payment_success_message').addClass('payment_message_active')
		
	} else {

		$('.payment_message_active').removeClass('payment_message_active')

		$('.payment_failure_message').addClass('payment_message_active')
		
	}

}

function payment_resize(){

}

function payment_scroll(){
		
}

$(document).ready(function() {

	$(window).on('resize.cms', payment_resize)
	
	$(window).on('scroll.cms', payment_scroll)
	
	payment_init()
	payment_resize()
	payment_scroll()

})
