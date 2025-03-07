var shop_checkout_payment_interval = false

function shop_checkout_init(){

	$('.shop_checkout_close,.shop_checkout_menu_item_basket').on('click.cms', function(e){
		location.reload()
	})

	$('.shop_checkout_menu_item_delivery').on('click.cms', function(e){
		
		$('.shop_checkout_tab').css({'display':'none'})
		$('.shop_checkout_tab_delivery').css({'display':''})
		
		$('.shop_checkout_menu_item_active').removeClass('shop_checkout_menu_item_active')
		$(this).addClass('shop_checkout_menu_item_active')
		
		$('.shop_checkout_menu_item_available').removeClass('shop_checkout_menu_item_available')
		$('.shop_checkout_menu_item_basket').addClass('shop_checkout_menu_item_available')
	
	})
	
	$('.shop_checkout_menu_item_review').on('click.cms', function(e){
		
		$('.shop_checkout_tab').css({'display':'none'})
		$('.shop_checkout_tab_review').css({'display':''})
	
		$('.shop_checkout_menu_item_active').removeClass('shop_checkout_menu_item_active')
		$(this).addClass('shop_checkout_menu_item_active')
	
		$('.shop_checkout_menu_item_available').removeClass('shop_checkout_menu_item_available')
		$('.shop_checkout_menu_item_basket').addClass('shop_checkout_menu_item_available')
		$('.shop_checkout_menu_item_delivery').addClass('shop_checkout_menu_item_available')

	})
	
	$('.shop_checkout_menu_item_payment').on('click.cms', function(e){
		
		$('.shop_checkout_tab').css({'display':'none'})
		$('.shop_checkout_tab_payment').css({'display':''})

		$('.shop_checkout_menu_item_active').removeClass('shop_checkout_menu_item_active')
		$(this).addClass('shop_checkout_menu_item_active')
	
		$('.shop_checkout_menu_item_available').removeClass('shop_checkout_menu_item_available')
		$('.shop_checkout_menu_item_basket').addClass('shop_checkout_menu_item_available')
		$('.shop_checkout_menu_item_delivery').addClass('shop_checkout_menu_item_available')
		$('.shop_checkout_menu_item_review').addClass('shop_checkout_menu_item_available')
		
		// start payment check
		if (shop_checkout_payment_interval !== false){
			clearInterval(shop_checkout_payment_interval)
		}
		
		shop_checkout_payment_interval = setInterval(shop_checkout_payment_check, 3000)

	})
	
	$('.shop_checkout_tab').css({'display':'none'})
	$('.shop_checkout_menu_item_' + $('.shop_checkout_container').data('active')).click()
	
	// delivery
	
	$('.shop_checkout_delivery_method').on('click.cms', function(){
		
		var method_id = $(this).data('method_id')
		
		get_ajax_panel(
			'shop/checkout', 
			{
				'do':'delivery_method',
				'method_id':method_id
			}, 
			result => {				
				// $('.userbasket_area').html(result.result.html)
				$('.shop_basket_area').html(result.result.html)
			}
		)

	})
	
	$('.shop_checkout_delivery_change').on('click.cms', function(){
		
		get_ajax_panel(
			'shop/checkout', 
			{
				'do':'delivery_change'
			}, 
			result => {				
				$('.shop_basket_area').html(result.result.html)
			}
		)
		
	})
	
	$('.shop_checkout_delivery_save').on('click.cms', function(){

		get_ajax_panel(
			'shop/checkout', 
			{
				'do':'delivery_address',
				'checkout_input_address1':$('.shop_checkout_input_address1').val(),
				'checkout_input_address2':$('.shop_checkout_input_address2').val(),
				'checkout_input_address3':$('.shop_checkout_input_address3').val(),
				'checkout_input_postcode':$('.shop_checkout_input_postcode').val(),
				'checkout_input_county':$('.shop_checkout_input_county').val(),
				'checkout_input_country':$('.shop_checkout_input_country').val(),
				'checkout_input_email':$('.shop_checkout_input_email').val(),
				'checkout_input_name':$('.shop_checkout_input_name').val(),
				'checkout_input_phone':$('.shop_checkout_input_phone').val()
			}, 
			result => {				
				$('.shop_basket_area').html(result.result.html)
			}
		)

	})

	// pay
	
	$('.shop_checkout_pay').on('click.cms', function(){
		
		$('.shop_checkout_menu_item_payment').click()
		
	})

}

function shop_checkout_resize(){

}

function shop_checkout_scroll(){

}

function shop_checkout_payment_check(){
	
	get_ajax_panel(
		'shop/worldpay_check', 
		{
			'do':'check',
			'number':$('.shop_checkout_container').data('number')
		}, 
		result => {

			if (result.result.status == 'paid'){
				clearInterval(shop_checkout_payment_interval)
				$('.shop_checkout_menu').remove()
				$('.shop_checkout_tabs').html(result.result.html)
				
				if (typeof fbq !== 'undefined'){
					fbq('track', 'Purchase', {currency: 'GBP', value: $('.shop_checkout_review_topay').data('amount')})
				}
				
				if (typeof analytics_trackers !== 'undefined'){
					analytics_send('event', 'shop', 'purchase', '', $('.shop_checkout_review_topay').data('amount'))
				}

				if (typeof headerbasket_update == 'function'){
					headerbasket_update()
				}
				
				if ($('.shop_checkout_container').data('success_page')){
					setTimeout(() => {
						window.location = _cms_base + $('.shop_checkout_container').data('success_page')
					}, 1000)
				}

			}
			
			if (result.result.status == 'problem'){
				clearInterval(shop_checkout_payment_interval)
				$('.shop_checkout_menu').remove()
				$('.shop_checkout_tabs').html(result.result.html)
			}

		}
	)

}

$(document).ready(function() {

	$(window).on('resize.cms', shop_checkout_resize)
	$(window).on('scroll.cms', shop_checkout_scroll)
	shop_checkout_init()
	shop_checkout_resize()
	shop_checkout_scroll()

})
