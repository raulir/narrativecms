/**
 * My subscription: sync with Stripe, auto-renew toggle, change plan, purchase.
 */

function manage_notify(msg, is_error){

	// pricing.js is loaded with this panel
	if (typeof pricing_notify === 'function'){
		pricing_notify(msg, is_error)
	}

}

function manage_panel_id($container){

	return parseInt($container.attr('data-cms_page_panel_id') || '0', 10) || 0

}

// The action's reply (definition ajax.output), under result
function manage_body(result){

	return (result && result.result) || {}

}

// A failed action: the server's reason when it gave one, else the page's text (Subscription settings)
function manage_fail($container, body, text_attr){

	manage_notify((body && body.cms_error) || $container.attr(text_attr), true)

}

function manage_reload_page(){

	// A "Payment received" just shown is shown again after the reload (pricing.js)
	if (typeof pricing_paid_carry === 'function'){
		pricing_paid_carry()
	}
	window.location.reload()

}

function manage_open_redirect(url){

	// The Android app opens it in the device browser; the website and the web app in the same window
	if (cms_platform() === 'android'){
		cms_open_external(url)
		return
	}
	window.location.href = url

}

function manage_sync($container){

	var data = { 'cms_do': 'sync_subscription' }
	var pid = manage_panel_id($container)
	if (pid){
		data.cms_page_panel_id = pid
	}

	data.success = function(result){

		if (String(manage_body(result).changed) === '1'){
			manage_reload_page()
		}

	}

	get_ajax('subscription/manage', data)

}

function manage_set_auto_renew($container, on){

	var $period = $container.find('.manage_status_period')
	var updating = $container.attr('data-manage_updating')
	var prev = $period.text()
	$period.text(updating)
	$container.find('.manage_auto_option').addClass('manage_auto_busy')

	var data = {
		'cms_do': 'set_auto_renew',
		'auto_renew': on ? '1' : '0',
	}
	var pid = manage_panel_id($container)
	if (pid){
		data.cms_page_panel_id = pid
	}

	data.success = function(result){

		$container.find('.manage_auto_option').removeClass('manage_auto_busy')
		var body = manage_body(result)
		if (String(body.ok) !== '1'){
			$period.text(prev)
			manage_fail($container, body, 'data-manage_update_error')
			return
		}

		if (body.redirect){
			$period.text(prev)
			manage_open_redirect(body.redirect)
			return
		}

		var is_on = !!body.auto_renew_on
		$container.find('.manage_auto_option').removeClass('manage_auto_option_active')
		$container.find('.manage_auto_option[data-auto_renew="' + (is_on ? '1' : '0') + '"]').addClass('manage_auto_option_active')
		if (body.status_period_line){
			$period.text(body.status_period_line)
		} else {
			$period.text(prev)
		}

	}

	data.error = function(){

		$container.find('.manage_auto_option').removeClass('manage_auto_busy')
		$period.text(prev)
		manage_fail($container, null, 'data-manage_update_error')

	}

	get_ajax('subscription/manage', data)

}

function manage_change_plan($container, product_id, currency_id){

	var data = {
		'cms_do': 'change_plan',
		'product_id': product_id,
		'currency_id': currency_id,
	}
	var pid = manage_panel_id($container)
	if (pid){
		data.cms_page_panel_id = pid
	}

	$container.addClass('pricing_checkout_loading')

	data.success = function(result){

		$container.removeClass('pricing_checkout_loading')
		var body = manage_body(result)
		if (String(body.ok) !== '1'){
			manage_fail($container, body, 'data-manage_change_error')
			return
		}
		if (body.redirect){
			manage_open_redirect(body.redirect)
			return
		}
		manage_reload_page()

	}

	data.error = function(){

		$container.removeClass('pricing_checkout_loading')
		manage_fail($container, null, 'data-manage_change_error')

	}

	get_ajax('subscription/manage', data)

}

function manage_init($root){

	var $scope = $root ? $root.find('.pricing_manage') : $('.pricing_manage')

	$scope.not('.manage_ok').each(function(){

		var $container = $(this)
		$container.addClass('manage_ok')

		// shop/currency_selector (basic): reload with currency_id — server emits only that currency's cards
		$container.find('.currency_selector_value').on('change.cms', function(){

			var cid = String($(this).val() || '0')
			if (!cid || cid === '0'){
				return
			}
			var url = new URL(window.location.href)
			url.searchParams.set('currency_id', cid)
			window.location.href = url.toString()

		})

		// Purchase (basic)
		$container.find('.pricing_cta_paid').on('click.cms', function(){

			if (typeof pricing_purchase_click === 'function'){
				pricing_purchase_click($container, $(this))
			}

		})

		// Change plan (premium)
		$container.find('.pricing_cta_change').on('click.cms', function(){

			var product_id = parseInt($(this).attr('data-product_id') || '0', 10) || 0
			var currency_id = parseInt(
					$(this).attr('data-currency_id') || $container.attr('data-currency_id') || '0',
					10
			) || 0
			manage_change_plan($container, product_id, currency_id)

		})

		// Auto-renew toggle (inline under status)
		$container.find('.manage_auto_option').on('click.cms', function(){

			if ($(this).hasClass('manage_auto_option_active') || $(this).hasClass('manage_auto_busy')){
				return
			}
			var on = String($(this).attr('data-auto_renew') || '0') === '1'
			manage_set_auto_renew($container, on)

		})

		// Google Play's subscription page: the device browser or Play from the Android app, a new tab elsewhere
		$container.find('.manage_play_cta').on('click.cms', function(event){
			event.preventDefault()
			cms_open_external($(this).attr('href'))
		})

		// Update payment method → Stripe portal (payment_method_update only)
		$container.find('.manage_payment_cta').on('click.cms', function(){

			var data = {
				'cms_do': 'update_payment_method',
				'return_url': window.location.pathname + window.location.search,
			}
			// The installed web app: the page path the backend knows, and the shell root to come back into
			if (cms_platform() === 'web_app'){
				data.return_url = cms_app_strip_root(window.location.pathname) + window.location.search
				data.app_root_url = location.origin + cms_app_root()
			}
			var pid = manage_panel_id($container)
			if (pid){
				data.cms_page_panel_id = pid
			}

			data.success = function(result){

				var body = manage_body(result)
				if (String(body.ok) !== '1' || !body.redirect){
					manage_fail($container, body, 'data-manage_payment_error')
					return
				}
				manage_open_redirect(body.redirect)

			}

			data.error = function(){
				manage_fail($container, null, 'data-manage_payment_error')
			}

			get_ajax('subscription/manage', data)

		})

		// Server already emitted only allowed cards (data-cards_server=1) — no filter JS

		// Async re-check CMS vs Stripe
		if (String($container.attr('data-user_logged_in') || '') === '1'){
			manage_sync($container)
		}

		// A checkout started from the app: watch until it is paid (pricing.js)
		if (typeof pricing_watch_resume === 'function'){
			pricing_watch_resume($container)
		}

	})

}

$(document).ready(function(){
	manage_init()
})
