/**
 * My subscription: sync with Stripe, auto-renew toggle, change plan, purchase.
 */

function manage_notify(msg, is_error){

	if (typeof pricing_notify === 'function'){
		pricing_notify(msg, is_error)
		return
	}
	if (window.alert){
		window.alert(msg)
	}

}

function manage_panel_id($container){

	return parseInt($container.attr('data-cms_page_panel_id') || '0', 10) || 0

}

function manage_reload_page(){

	window.location.reload()

}

function manage_sync($container){

	var data = { 'do': 'sync_subscription' }
	var pid = manage_panel_id($container)
	if (pid){
		data.cms_page_panel_id = pid
	}

	data.success = function(result){

		var body = result && result.result ? result.result : result
		if (body && body.changed){
			manage_reload_page()
		}

	}

	get_ajax('subscription/manage', data)

}

function manage_set_auto_renew($container, on){

	var $period = $container.find('.manage_status_period')
	var updating = $container.attr('data-manage_updating') || 'Updating …'
	var prev = $period.text()
	$period.text(updating)
	$container.find('.manage_auto_option').addClass('manage_auto_busy')

	var data = {
		'do': 'set_auto_renew',
		'auto_renew': on ? '1' : '0',
	}
	var pid = manage_panel_id($container)
	if (pid){
		data.cms_page_panel_id = pid
	}

	data.success = function(result){

		$container.find('.manage_auto_option').removeClass('manage_auto_busy')
		var body = result && result.result ? result.result : result
		if (!body || !body.ok){
			$period.text(prev)
			manage_notify((body && body.error) ? body.error : 'Could not update', true)
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
		manage_notify('Could not update', true)

	}

	get_ajax('subscription/manage', data)

}

function manage_change_plan($container, product_id, currency_id){

	var data = {
		'do': 'change_plan',
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
		var body = result && result.result ? result.result : result
		if (!body || !body.ok){
			manage_notify((body && body.error) ? body.error : 'Could not change plan', true)
			return
		}
		manage_reload_page()

	}

	data.error = function(){

		$container.removeClass('pricing_checkout_loading')
		manage_notify('Could not change plan', true)

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

		// Update payment method → Stripe portal (payment_method_update only)
		$container.find('.manage_payment_cta').on('click.cms', function(){

			var data = {
				'do': 'update_payment_method',
				'return_url': window.location.pathname + window.location.search,
			}
			var pid = manage_panel_id($container)
			if (pid){
				data.cms_page_panel_id = pid
			}

			data.success = function(result){

				var body = result && result.result ? result.result : result
				if (!body || !body.ok || !body.redirect){
					manage_notify((body && body.error) ? body.error : 'Could not open payment settings', true)
					return
				}
				window.location.href = body.redirect

			}

			data.error = function(){
				manage_notify('Could not open payment settings', true)
			}

			get_ajax('subscription/manage', data)

		})

		// Server already emitted only allowed cards (data-cards_server=1) — no filter JS

		// Async re-check CMS vs Stripe
		if (String($container.attr('data-user_logged_in') || '') === '1'){
			manage_sync($container)
		}

	})

}

$(document).ready(function(){
	manage_init()
})
