/**
 * Pricing: filter cards; free CTA is a link; paid CTA → login intent or checkout provider.
 */

function pricing_notify(msg, is_error){

	if (typeof cms_notification === 'function'){
		cms_notification(msg, 3, is_error ? 'error' : 'success')
		return
	}
	if (is_error && typeof cms_error === 'function'){
		cms_error(msg, 3)
		return
	}
	if (window.alert){
		window.alert(msg)
	}

}

function pricing_apply_filter($container){

	var layout = $container.attr('data-layout') || 'filter'
	var interval = $container.attr('data-interval') || 'month'
	var currency_id = String($container.attr('data-currency_id') || '0')

	$container.find('.pricing_card').each(function(){

		var $card = $(this)
		if ($card.attr('data-always') === '1'){
			$card.removeClass('pricing_card_hidden')
			return
		}

		var card_interval = $card.attr('data-interval') || ''
		var card_currency = String($card.attr('data-currency_id') || '0')
		var match
		if (layout === 'side_by_side'){
			// Manage: month + year for selected currency side by side
			match = (card_currency === currency_id)
		} else {
			match = (card_interval === interval && card_currency === currency_id)
		}

		$card.toggleClass('pricing_card_hidden', !match)

	})

}

function pricing_checkout_body(result){

	if (result && result.result && (result.result.redirect || result.result.ok !== undefined || result.result.login !== undefined)){
		return result.result
	}
	return result

}

function pricing_start_checkout($container, product_id, currency_id){

	// Domain entry only — provider is chosen server-side in subscription/checkout_start
	var missing = String($container.attr('data-checkout_missing') || '') === '1'
	var missing_msg = $container.attr('data-checkout_missing_message') || 'Select subscription checkout provider!'
	var error_msg = $container.attr('data-checkout_error_message') || 'Checkout could not start. Try again.'

	if (missing){
		pricing_notify(missing_msg, true)
		return
	}

	product_id = parseInt(product_id, 10) || 0
	currency_id = parseInt(currency_id, 10) || 0
	if (product_id < 1){
		pricing_notify(error_msg, true)
		return
	}

	$container.addClass('pricing_checkout_loading')

	get_ajax_panel('subscription/checkout_start', {
		'do': 'subscription_checkout',
		'product_id': product_id,
		'currency_id': currency_id,
	}, function(result){

		$container.removeClass('pricing_checkout_loading')
		var body = pricing_checkout_body(result)

		if (body && body.login){
			var login_url = body.login_url || $container.attr('data-login_url') || ''
			if (login_url){
				window.location.href = login_url
				return
			}
		}

		// Includes already_subscribed → /start/ (or user link) from checkout_start
		if (body && body.redirect){
			window.location.href = body.redirect
			return
		}

		var err = (body && body.error) ? body.error : error_msg
		pricing_notify(err, true)

	})

}

function pricing_purchase_click($container, $cta){

	var product_id = parseInt($cta.attr('data-product_id') || '0', 10) || 0
	var currency_id = parseInt(
			$cta.attr('data-currency_id') || $container.attr('data-currency_id') || '0',
			10
	) || 0
	var logged_in = String($container.attr('data-user_logged_in') || '') === '1'
	var panel_id = parseInt($container.attr('data-cms_page_panel_id') || '0', 10) || 0

	if (logged_in){
		pricing_start_checkout($container, product_id, currency_id)
		return
	}

	// Guest: store intent then go to login (path-only resume — safe same-site after auth)
	var data = {
		'do': 'set_checkout_intent',
		'product_id': product_id,
		'currency_id': currency_id,
		'resume_url': window.location.pathname + window.location.search,
	}
	if (panel_id){
		data.cms_page_panel_id = panel_id
	}

	data.success = function(result){
		var body = result && result.result ? result.result : result
		var login_url = (body && body.login_url) ? body.login_url : ($container.attr('data-login_url') || '')
		if (login_url){
			window.location.href = login_url
			return
		}
		pricing_notify($container.attr('data-checkout_error_message') || 'Checkout could not start.', true)
	}

	get_ajax('subscription/pricing', data)

}

function pricing_init($root){

	var $scope = $root ? $root.find('.pricing_container') : $('.pricing_container')

	$scope.not('.pricing_ok').each(function(){

		var $container = $(this)
		$container.addClass('pricing_ok')

		// shop/currency_selector → filter cards by #currency_selector_value / .currency_selector_value
		var $currency_input = $container.find('.currency_selector_value').first()
		if ($currency_input.length){
			var init_cid = String($currency_input.val() || '0')
			if (init_cid && init_cid !== '0'){
				$container.attr('data-currency_id', init_cid)
			}
			$currency_input.on('change.cms', function(){
				var cid = String($(this).val() || '0')
				$container.attr('data-currency_id', cid)
				pricing_apply_filter($container)
			})
		}

		// Public pricing only — manage uses side_by_side (no toggle)
		if (($container.attr('data-layout') || 'filter') !== 'side_by_side'){
			$container.find('.pricing_toggle_option').on('click.cms', function(e){

				e.preventDefault()
				var interval = $(this).attr('data-interval') || 'month'
				$container.attr('data-interval', interval)
				$container.find('.pricing_toggle_option').removeClass('pricing_toggle_active')
				$(this).addClass('pricing_toggle_active')
				pricing_apply_filter($container)

			})
		}

		$container.find('.pricing_cta_paid').on('click.cms', function(){
			pricing_purchase_click($container, $(this))
		})

		pricing_apply_filter($container)

		// Post-login resume
		if (String($container.attr('data-auto_checkout') || '') === '1'){
			var pid = parseInt($container.attr('data-auto_product_id') || '0', 10) || 0
			var cid = parseInt($container.attr('data-auto_currency_id') || '0', 10) || 0
			if (pid > 0){
				pricing_start_checkout($container, pid, cid)
			}
		}

	})

}

function pricing_resize(){
}

function pricing_scroll(){
}

$(document).ready(function(){
	pricing_init()
	$(window).on('resize.cms', pricing_resize)
	$(window).on('scroll.cms', pricing_scroll)
})
