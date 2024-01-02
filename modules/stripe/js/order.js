function order_init(){

	order_load_stripe().then(() => {

		$('.order_plans_plan').on('click.cms', function(){
			
			$('.order_plans_plan_active').removeClass('order_plans_plan_active')
			$(this).addClass('order_plans_plan_active')
			
			// update available number of licences
			order_number().then(order_periods).then(order_calculate)
	
		})
		
		$('.order_container').on('change.cms', '.order_number_input', function(){
			order_calculate()
		})
		
		$('.order_container').on('click.cms', '.order_periods_period', function(){
		
			$('.order_periods_period_active').removeClass('order_periods_period_active')
			$(this).addClass('order_periods_period_active')
			
			order_calculate()
			
		})
		
		var hash = window.location.hash.substr(1)
		if (hash){
			$('.order_plans_plan_' + hash).click()
		}
		
		$('.order_button_continue').on('click.cms', function(){
		
			if ($(this).hasClass('order_button_continue_disabled')){
				return
			}
			
			if($('.order_container').data('cp_user_id')){
				
				// load payment
				get_ajax_panel('dashboard/payment', {
					'cp_user_id': $('.order_container').data('cp_user_id'),
					'plan_id': $('.order_plans_plan_active').data('plan_id'),
					'plan_licences': $('.order_number_input').val(),
					'plan_period': $('.order_periods_period_active').data('period_id'),
				}).then(result => {
					$('.order_content').append(result.result.html)
				})
				
			} else {
				
				// load signup
				get_ajax_panel('dashboard/signup', {
					'success_click': 'order_button_continue',
					'button_label': $('.order_container').data('register_button_label'),
					'heading': $('.order_container').data('register_heading'),
					'back_label': $('.order_container').data('register_back_label'),
					'close_class': 'order_popup_register',
				}).then(result => {
					$('body').append('<div class="order_popup_register signup_popup">' + result.result.html + '</div>')
				})
	
			}
			
		})
		
		$('.order_button_cancel').on('click.cms', () => location = '/plans')
		
	})
		
}

function order_load_stripe(){
	
	return new Promise((resolve, reject) => {

		if (typeof Stripe == 'undefined'){ 
			let script = document.createElement('script')
		    document.head.appendChild(script)
		    script.type = 'text/javascript'
		    script.addEventListener('load',resolve)
		    script.src = 'https://js.stripe.com/v3/'
		} else {
			resolve()
		}

	})
	
}

function order_number(){

	return new Promise((resolve, reject) => {

		get_ajax_panel('dashboard/order_number', {
			'plan_id': $('.order_plans_plan_active').data('plan_id'),
			'selected': $('.order_number_input').val(),
		}).then(result => {
			$('.order_number').html(result.result.html)
			resolve()
		})
	
	})
	
}

function order_periods(){

	return new Promise((resolve, reject) => {

		get_ajax_panel('dashboard/order_periods', {
			'plan_id': $('.order_plans_plan_active').data('plan_id'),
			'selected': $('.order_periods_period_active').data('period_id'),
		}).then(result => {
			$('.order_periods').html(result.result.html)
			resolve()
		})
	
	})
	
}

function order_calculate(){
	
	var plan_id = $('.order_plans_plan_active').data('plan_id')
	var plan_licences = $('.order_number_input').val()
	var plan_period = $('.order_periods_period_active').data('period_id')
	
	get_ajax_panel('dashboard/order_calculate', {
		'plan_id': plan_id,
		'plan_licences': plan_licences,
		'plan_period': plan_period,
	}).then(result => {
		$('.order_calculate').html(result.result.html)
		
		if (result.result.locked || !result.result.show_selected){
			$('.order_button_continue').addClass('order_button_continue_disabled')
		} else {
			$('.order_button_continue_disabled').removeClass('order_button_continue_disabled')
		}
	})
	
}

function order_resize(){

}

function order_scroll(){
		
}

$(document).ready(function() {

	$(window).on('resize.cms', order_resize)
	
	$(window).on('scroll.cms', order_scroll)
	
	order_init()
	order_resize()
	order_scroll()

})
