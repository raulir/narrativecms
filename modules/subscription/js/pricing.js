/**
 * Pricing: filter cards; free CTA is a link; paid CTA → login intent or checkout provider.
 */

/**
 * A notice with OK: the site's popup when a module provides one (CMS hook popup), else the notice bar.
 */
function pricing_notify(msg){

	var popup = (typeof cms_hook === 'function') ? cms_hook('popup') : null
	if (popup){
		popup({text: msg})
		return
	}
	pricing_paid_notice(msg)

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

// A panel action's reply (subscription/checkout_start, subscription/pricing): its keys are under result
function pricing_checkout_body(result){

	return (result && result.result) || null

}

/**
 * The installed web app (in-app pages without the Android bridge): its shell root, e.g.
 * https://scoretutor.com/app/, so Stripe returns into it. '' on the website and in the Android app.
 */
function pricing_web_app_root(){

	if (cms_platform() !== 'web_app'){
		return ''
	}
	return location.origin + cms_app_root()

}

/**
 * Go to a checkout answer's address: Stripe (absolute) as is; a site path inside the web app stays in it.
 */
function pricing_go(url){

	if (pricing_web_app_root() && String(url).charAt(0) === '/' && String(url).charAt(1) !== '/'){
		if (typeof cms_position_goto === 'function'){
			cms_position_goto(url)
			return
		}
		window.location.href = cms_app_url(url)
		return
	}
	window.location.href = url

}

/**
 * The paid-status watch. A checkout started here may be paid somewhere else: in the app, Stripe runs outside the
 * app window; on the website, Stripe returns before its webhook has written the plan. For 30 min after the start
 * the plans page and My subscription ask whether the account is paid now: every 5 s for the first 2 min, then
 * every 15 s, and at once when the page shows again. Paid: "Payment received" and My subscription with the new
 * plan. The start time is kept, so another page or a reload in that time watches on.
 */
var PRICING_WATCH_KEY = 'subscription_checkout_started'
var PRICING_PAID_KEY = 'subscription_paid_notice'
var PRICING_WATCH_MS = 30 * 60 * 1000
var PRICING_WATCH_EVERY = 5000
var PRICING_WATCH_SLOW = 15000
var PRICING_WATCH_SLOW_AFTER = 2 * 60 * 1000
// Kept when this file runs again (an app page can bring its scripts more than once)
var pricing_watch = window.pricing_watch || null

function pricing_watch_store(time){

	try {
		if (time){
			localStorage.setItem(PRICING_WATCH_KEY, String(time))
		} else {
			localStorage.removeItem(PRICING_WATCH_KEY)
		}
	} catch (e) {}

}

function pricing_watch_started(){

	try {
		return parseInt(localStorage.getItem(PRICING_WATCH_KEY) || '0', 10) || 0
	} catch (e) {
		return 0
	}

}

function pricing_watch_stop(clear){

	if (pricing_watch){
		clearTimeout(pricing_watch.timer)
		document.removeEventListener('visibilitychange', pricing_watch.visible)
		pricing_watch = null
	}
	if (clear){
		pricing_watch_store(0)
	}

}

function pricing_watch_paid($container, started){

	if (!$container || !$container.length){
		return
	}

	started = started || Date.now()
	pricing_watch_store(started)
	pricing_watch_stop(false)

	var watch = {
		$container: $container,
		started: started,
		busy: false,
	}
	pricing_watch_next(watch)
	watch.visible = function(){
		if (document.visibilityState === 'visible'){
			pricing_watch_check(watch)
		}
	}
	document.addEventListener('visibilitychange', watch.visible)
	pricing_watch = watch

}

/**
 * The next question: every 5 s for the first 2 min, then every 15 s.
 */
function pricing_watch_next(watch){

	clearTimeout(watch.timer)
	var every = (Date.now() - watch.started < PRICING_WATCH_SLOW_AFTER) ? PRICING_WATCH_EVERY : PRICING_WATCH_SLOW
	watch.timer = setTimeout(function(){
		pricing_watch_check(watch)
		if (pricing_watch === watch){
			pricing_watch_next(watch)
		}
	}, every)

}

/**
 * A checkout started in the last 30 min: watch on from this plans or My subscription page, asking at once
 * (back from Stripe on the website). A My subscription page that already shows a paid plan only says so.
 */
function pricing_watch_resume($container){

	// Back from a cancelled Stripe Checkout (its cancel address says so): nothing to wait for
	if (/[?&]subscription_checkout=cancel(&|$)/.test(location.search)){
		pricing_watch_stop(true)
		return
	}
	if (pricing_watch || !$container || !$container.length){
		return
	}
	var started = pricing_watch_started()
	if (started && Date.now() - started >= PRICING_WATCH_MS){
		pricing_watch_store(0)
		return
	}
	if (!started){
		return
	}
	if ($container.attr('data-manage_view') === 'premium'){
		pricing_watch_store(0)
		pricing_paid_say($container)
		return
	}
	pricing_watch_paid($container, started)
	pricing_watch_check(pricing_watch)

}

function pricing_watch_check(watch){

	if (pricing_watch !== watch || watch.busy){
		return
	}
	if (Date.now() - watch.started > PRICING_WATCH_MS){
		pricing_watch_stop(true)
		return
	}
	// Another page: this watch ends; the kept start time lets the plans page pick it up again
	if (!document.body.contains(watch.$container[0])){
		pricing_watch_stop(false)
		return
	}
	if (window._cms_offline){
		return
	}

	watch.busy = true
	get_ajax('subscription/pricing', {
		'cms_do': 'paid_status',
		'success': function(result){
			watch.busy = false
			var body = result && result.result ? result.result : result
			if (pricing_watch !== watch || !body || String(body.paid) !== '1'){
				return
			}
			pricing_watch_stop(true)
			pricing_paid_refresh(watch.$container)
		},
		'error': function(){
			watch.busy = false
		},
	})

}

/**
 * The notice bar of a site without a popup: shows for 5 s.
 */
function pricing_paid_notice(text){

	$('.pricing_paid_notice').remove()
	var $el = $('<div class="pricing_paid_notice"></div>').text(text)
	$('body').append($el)
	setTimeout(function(){
		$el.addClass('pricing_paid_notice_hide')
		setTimeout(function(){
			$el.remove()
		}, 600)
	}, 5000)

}

/**
 * "Payment received" (setting paid_message): the site's popup when there is one, else the notice bar. On body,
 * so an in-app page load under it does not remove it; it closes itself.
 */
function pricing_paid_say($container){

	window.pricing_paid_said_at = Date.now()
	var paid_text = ($container && $container.attr('data-paid_message')) || 'Payment received'
	var popup = (typeof cms_hook === 'function') ? cms_hook('popup') : null
	if (popup){
		popup({text: paid_text, key: 'subscription_paid', close_after: 5000})
	} else {
		pricing_paid_notice(paid_text)
	}

}

/**
 * A website page is loaded again: "Payment received" said in the last 5 s is said on the new page too.
 */
function pricing_paid_carry(){

	if (window.pricing_paid_said_at && Date.now() - window.pricing_paid_said_at < 5000){
		try {
			sessionStorage.setItem(PRICING_PAID_KEY, '1')
		} catch (e) {}
	}

}

/**
 * A website page loaded after a paid watch: say "Payment received" there (the reload took the first one away).
 */
function pricing_paid_pending($container){

	var waiting = ''
	try {
		waiting = sessionStorage.getItem(PRICING_PAID_KEY) || ''
		sessionStorage.removeItem(PRICING_PAID_KEY)
	} catch (e) {}
	if (waiting && $container && $container.length){
		pricing_paid_say($container)
	}

}

/**
 * Paid: say so, and open My subscription (data-manage_url), in the app without leaving it.
 */
function pricing_paid_refresh($container){

	// The website loads a new page: the notice is said there
	if (cms_platform() === 'site'){
		try {
			sessionStorage.setItem(PRICING_PAID_KEY, '1')
		} catch (e) {}
	} else {
		pricing_paid_say($container)
	}

	// My subscription shows the new plan: open it, or load it again when it is the page shown
	var current = (typeof cms_app_strip_root === 'function') ? cms_app_strip_root(location.pathname) : location.pathname
	var manage = String($container.attr('data-manage_url') || '')
	if (manage.charAt(0) !== '/' || manage.charAt(1) === '/'){
		manage = ''
	}
	var same = !manage || manage === current

	if (cms_platform() !== 'site' && typeof cms_position_goto === 'function'){
		cms_position_goto(same ? current + location.search : manage, same ? {history: 'none', reload: true} : {})
		return
	}
	if (same){
		window.location.reload()
		return
	}
	window.location.href = manage

}

function pricing_start_checkout($container, product_id, currency_id, play_product_id){

	// The Android app: the payment choice screen of the Play module (CMS hook payment_choice), which starts
	// the paid-status watch once a way is chosen. Without it nothing is sold in the app. The web app pays
	// as the website does.
	if (cms_platform() === 'android'){
		var choice = cms_hook('payment_choice')
		if (!choice){
			pricing_notify($container.attr('data-checkout_error_message') || 'Checkout could not start. Try again.')
			return
		}
		choice({
			$container: $container,
			product_id: product_id,
			currency_id: currency_id,
			play_product_id: play_product_id || '',
			page_path: $container.attr('data-page_path') || '',
		})
		return
	}

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

	var data = {
		'cms_do': 'subscription_checkout',
		'product_id': product_id,
		'currency_id': currency_id,
	}
	var app_root = pricing_web_app_root()
	if (app_root){
		data.app_root_url = app_root
	}

	data.error = function(){
		$container.removeClass('pricing_checkout_loading')
		pricing_notify(error_msg, true)
	}
	data.success = function(result){

		$container.removeClass('pricing_checkout_loading')
		var body = pricing_checkout_body(result)

		if (body && body.login){
			var login_url = body.login_url || $container.attr('data-login_url') || ''
			if (login_url){
				pricing_go(login_url)
				return
			}
		}

		// Stripe Checkout, or already_subscribed → /start/ (or user link) from checkout_start
		if (body && body.redirect){
			if (/^https?:\/\//i.test(String(body.redirect))){
				pricing_watch_paid($container)
			}
			pricing_go(body.redirect)
			return
		}

		pricing_notify((body && body.cms_error) || error_msg, true)

	}

	get_ajax('subscription/checkout_start', data).catch(function(){})

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
		pricing_start_checkout($container, product_id, currency_id, $cta.attr('data-play_product_id') || '')
		return
	}

	// Guest: store intent then go to login (path-only resume — safe same-site after auth)
	var data = {
		'cms_do': 'set_checkout_intent',
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

	// My subscription (.pricing_manage) is set up by manage.js, which calls the shared parts itself; the
	// checkout watch also starts from here, whichever of the two files runs last
	pricing_paid_pending($scope.first())
	pricing_watch_resume($scope.filter('.pricing_manage').first())

	$scope.not('.pricing_ok').not('.pricing_manage').each(function(){

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

		pricing_watch_resume($container)

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
