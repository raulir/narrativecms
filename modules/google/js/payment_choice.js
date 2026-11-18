/**
 * The Android app's payment choice (google.md § App choice): Purchase on the plans page or My subscription
 * shows this screen in place of the plans. Google Play is the default; ScoreTutor (Stripe) goes through
 * Play's external-transaction token and Play's own link screen. Without Play Billing nothing can be bought here.
 * The app calls the replies by name: app_play_purchase_result, app_play_external_token, app_play_link_result.
 */
var payment_choice = window.payment_choice || null

function payment_choice_text(key){

	return payment_choice ? String(payment_choice.$screen.attr('data-' + key) || '') : ''

}

function payment_choice_play_ready(){

	return !!(window.AppSplash && AppSplash.playReady && String(AppSplash.playReady()) === '1')

}

/**
 * Which ways can be used now; selects Google Play when it can be, else Stripe, and greys the rest.
 */
function payment_choice_refresh(){

	var state = payment_choice
	if (!state || state.busy){
		return
	}

	var ready = payment_choice_play_ready()
	var has_play = state.play_product_id !== ''
	var usable = {play: ready && has_play, stripe: ready}
	var $options = state.$screen.find('.payment_choice_option')

	$options.each(function(){
		var $option = $(this)
		var way = $option.attr('data-way')
		$option.toggleClass('payment_choice_option_disabled', !usable[way])
		$option.attr('aria-disabled', usable[way] ? 'false' : 'true')
		$option.find('.payment_choice_option_reason').text(way === 'play' && ready && !has_play ? payment_choice_text('no_play_product') : '')
	})

	if (!usable[state.way]){
		state.way = usable.play ? 'play' : (usable.stripe ? 'stripe' : '')
	}
	$options.each(function(){
		var on = $(this).attr('data-way') === state.way
		$(this).toggleClass('payment_choice_option_selected', on).attr('aria-checked', on ? 'true' : 'false')
	})

	state.$screen.find('.payment_choice_continue').prop('disabled', !state.way)
	if (!ready){
		payment_choice_message(payment_choice_text('unavailable'), false)
		state.message_kind = 'unavailable'
	} else if (state.message_kind === 'unavailable'){
		payment_choice_message('', false)
	}

}

function payment_choice_message(text, is_error){

	if (!payment_choice){
		return
	}
	payment_choice.message_kind = ''
	if (payment_choice.direct){
		if (text && typeof pricing_notify === 'function'){
			pricing_notify(text)
		}
		return
	}
	payment_choice.$screen.find('.payment_choice_message')
		.text(text || '')
		.toggleClass('payment_choice_message_error', !!is_error)

}

function payment_choice_busy(busy){

	var state = payment_choice
	if (!state){
		return
	}
	state.busy = !!busy
	if (state.direct){
		return
	}
	state.$screen.toggleClass('payment_choice_busy', state.busy)
	state.$screen.find('.payment_choice_continue').prop('disabled', state.busy || !state.way)
	if (state.busy){
		payment_choice_message(payment_choice_text('opening'), false)
	}

}

/**
 * A way could not be started or did not finish: say so in the screen's own (translated) words, and let the
 * user try again. detail: the app's or the server's reply, English, kept for the console only.
 */
function payment_choice_error(text, detail){

	// A bridge answer carries error, a server reply cms_error
	var why = detail ? (detail.error || detail.cms_error) : ''
	if (why && window.console){
		console.warn('payment choice: ' + why)
	}

	if (typeof pricing_watch_stop === 'function'){
		pricing_watch_stop(true)
	}
	if (!payment_choice){
		return
	}
	payment_choice_busy(false)
	payment_choice_message(text || payment_choice_text('error_start'), true)
	if (payment_choice.direct){
		payment_choice_close()
		return
	}
	payment_choice_refresh()

}

function payment_choice_close(){

	var state = payment_choice
	if (!state){
		return
	}
	clearInterval(state.poll)
	if (!state.direct){
		state.$screen.closest('.payment_choice_wrap').remove()
		state.$content.removeClass('payment_choice_hidden')
	}
	payment_choice = window.payment_choice = null

}

/**
 * The back button (music_app_back): an open choice screen goes back to the plans. True when it did.
 */
function payment_choice_back(){

	if (!payment_choice || payment_choice.direct){
		return false
	}
	payment_choice_close()
	return true

}

function payment_choice_continue(){

	var state = payment_choice
	if (!state || state.busy || !state.way || !payment_choice_play_ready()){
		payment_choice_refresh()
		return
	}

	payment_choice_busy(true)
	// From here the plans page asks whether the account is paid (pricing.js)
	if (typeof pricing_watch_paid === 'function'){
		pricing_watch_paid(state.$container)
	}

	if (state.way === 'play'){
		AppSplash.launchPlayPurchase(state.play_product_id, String(state.user_id || ''))
		return
	}
	AppSplash.requestExternalToken()

}

/**
 * Google Play settings has ScoreTutor beside Play turned off. Keep the plan and the user id, and open
 * the Play sheet. Errors use the plans page notice, because the choice screen is not shown.
 */
function payment_choice_play_now($container, $screen, page_path){

	payment_choice = window.payment_choice = {
		$container: $container,
		$content: $container.find('.pricing_content').first(),
		$screen: $screen,
		product_id: parseInt($screen.attr('data-product_id') || '0', 10) || 0,
		currency_id: parseInt($screen.attr('data-currency_id') || '0', 10) || 0,
		play_product_id: String($screen.attr('data-play_product_id') || ''),
		user_id: parseInt($screen.attr('data-user_id') || '0', 10) || 0,
		page_path: page_path || $container.attr('data-page_path') || '',
		way: 'play',
		direct: true,
		busy: false,
		message_kind: '',
		enter_url: '',
	}

	if (!payment_choice.play_product_id){
		payment_choice_error(payment_choice_text('no_play_product'))
		return
	}
	if (!payment_choice_play_ready()){
		payment_choice_error(payment_choice_text('unavailable'))
		return
	}

	payment_choice_continue()

}

/**
 * Show the screen for one plan. choice: $container (the plans container), product_id, currency_id,
 * play_product_id, page_path. Set as the CMS hook payment_choice, which pricing.js calls in the Android app.
 */
function payment_choice_open(choice){

	choice = choice || {}
	var $container = choice.$container
	if (!$container || !$container.length){
		return
	}
	payment_choice_close()

	var failed = function(){
		if (typeof pricing_notify === 'function'){
			pricing_notify($container.attr('data-checkout_error_message') || 'Checkout could not start. Try again.')
		}
	}

	get_ajax_panel('google/payment_choice', {
		'product_id': choice.product_id || 0,
		'currency_id': choice.currency_id || 0,
		'play_product_id': choice.play_product_id || '',
	}).then(function(result){

		var res = result && result.result ? result.result : {}
		var $screen = $(res.cms_html || res.html || '').filter('.payment_choice_container')
		if (!$screen.length){
			failed()
			return
		}

		// Billing choice off: the Play sheet for this plan, with no selection screen
		if ($screen.attr('data-choice_offer') !== '1'){
			payment_choice_play_now($container, $screen, choice.page_path || '')
			return
		}

		var $content = $container.find('.pricing_content').first()
		var $wrap = $('<div class="payment_choice_wrap"></div>').append($screen)
		$content.addClass('payment_choice_hidden').after($wrap)
		window.scrollTo(0, 0)

		payment_choice = window.payment_choice = {
			$container: $container,
			$content: $content,
			$screen: $screen,
			product_id: parseInt($screen.attr('data-product_id') || '0', 10) || 0,
			currency_id: parseInt($screen.attr('data-currency_id') || '0', 10) || 0,
			play_product_id: String($screen.attr('data-play_product_id') || ''),
			user_id: parseInt($screen.attr('data-user_id') || '0', 10) || 0,
			page_path: choice.page_path || $container.attr('data-page_path') || '',
			way: 'play',
			busy: false,
			message_kind: '',
			enter_url: '',
		}

		$screen.find('.payment_choice_option').on('click.cms keydown.cms', function(event){
			if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' '){
				return
			}
			if (!payment_choice || payment_choice.busy || $(this).hasClass('payment_choice_option_disabled')){
				return
			}
			payment_choice.way = $(this).attr('data-way')
			payment_choice_refresh()
		})
		$screen.find('.payment_choice_back').on('click.cms', payment_choice_close)
		$screen.find('.payment_choice_continue').on('click.cms', payment_choice_continue)

		payment_choice_refresh()
		// Asking the bridge also reconnects Play Billing; Google Play becomes usable once it is back
		payment_choice.poll = setInterval(function(){
			if (!payment_choice || !document.body.contains(payment_choice.$screen[0])){
				payment_choice_close()
				return
			}
			payment_choice_refresh()
		}, 2000)

	}).catch(failed)

}

// A panel action's reply (google/purchase, google/transaction, subscription/pricing): its keys are under result
function payment_choice_body(result){

	return (result && result.result) || null

}

function payment_choice_discard_link(url){

	var match = String(url || '').match(/[?&]app_enter=([a-f0-9]{32})/)
	if (match){
		get_ajax('subscription/pricing', {'cms_do': 'browser_discard', 'token': match[1]}).catch(function(){})
	}

}

/**
 * Play's sheet answered. A purchase is confirmed with Google on the server, then acknowledged.
 */
function app_play_purchase_result(result){

	var state = payment_choice
	if (!state){
		return
	}
	if (result && result.pending){
		payment_choice_busy(false)
		payment_choice_message(payment_choice_text('pending'), false)
		return
	}
	if (!result || !result.token){
		payment_choice_error(payment_choice_text('error_play'), result)
		return
	}

	var purchase_token = String(result.token)
	get_ajax('google/purchase', {
		'cms_do': 'confirm',
		'purchase_token': purchase_token,
		'google_package_id': state.play_product_id,
		'product_id': state.product_id,
		'currency': state.currency_id,
		'success': function(confirmed){
			var body = payment_choice_body(confirmed)
			if (!body || String(body.ok) !== '1'){
				payment_choice_error(payment_choice_text('error_confirm'), body)
				return
			}
			if (AppSplash.acknowledgePlayPurchase){
				AppSplash.acknowledgePlayPurchase(purchase_token)
			}
			var $container = state.$container
			if (typeof pricing_watch_stop === 'function'){
				pricing_watch_stop(true)
			}
			payment_choice_close()
			if (typeof pricing_paid_refresh === 'function'){
				pricing_paid_refresh($container)
			}
		},
		'error': function(){
			payment_choice_error(payment_choice_text('error_confirm'))
		}
	}).catch(function(){})

}

/**
 * ScoreTutor (Stripe): Play's token is saved with the plan, then Play opens its link screen and the browser.
 */
function app_play_external_token(result){

	var state = payment_choice
	if (!state){
		return
	}
	var token = result && result.token ? String(result.token) : ''
	if (!token){
		payment_choice_error(payment_choice_text('error_stripe'), result)
		return
	}

	get_ajax('google/transaction', {
		'cms_do': 'save',
		'token': token,
		'google_package_id': state.play_product_id,
		'product_id': state.product_id,
		'currency': state.currency_id,
		'success': function(saved){
			var saved_body = payment_choice_body(saved)
			if (!saved_body || String(saved_body.ok) !== '1'){
				payment_choice_error(payment_choice_text('error_save'))
				return
			}
			get_ajax('subscription/pricing', {
				'cms_do': 'browser_open',
				'page_path': state.page_path,
				'success': function(opened){
					var body = payment_choice_body(opened)
					var url = body && body.url ? String(body.url) : ''
					if (!url){
						payment_choice_error(payment_choice_text('error_checkout'))
						return
					}
					state.enter_url = url
					AppSplash.launchExternalLink(url, token)
				},
				'error': function(){
					payment_choice_error(payment_choice_text('error_checkout'))
				}
			}).catch(function(){})
		},
		'error': function(){
			payment_choice_error(payment_choice_text('error_save'))
		}
	}).catch(function(){})

}

/**
 * Play's link screen answered: the browser is open (the watch takes over), or it was not opened.
 */
function app_play_link_result(result){

	var state = payment_choice
	if (!state){
		return
	}
	if (!result || String(result.ok) !== '1'){
		payment_choice_discard_link(state.enter_url)
		payment_choice_error(payment_choice_text('error_open'), result)
		return
	}
	payment_choice_busy(false)
	payment_choice_message('', false)

}

/**
 * A Play purchase whose confirm never reached the server (no network, app closed) is still unacknowledged
 * in Play. When the plans page or My subscription opens in the Android app, the app lists this Google
 * account's purchases (app_play_purchases) and each such one is confirmed now. Google refunds a purchase
 * that is not acknowledged within 3 days.
 */
function payment_choice_recover_init(){

	if (typeof cms_platform !== 'function' || cms_platform() !== 'android' || !AppSplash.queryPlayPurchases){
		return
	}
	var $container = $('.pricing_container').not('.payment_choice_recover_asked').first()
	if (!$container.length){
		return
	}
	$container.addClass('payment_choice_recover_asked')
	window.payment_choice_recover_container = $container
	AppSplash.queryPlayPurchases()

}

function app_play_purchases(result){

	var list = result && Array.isArray(result.purchases) ? result.purchases : []
	var $container = window.payment_choice_recover_container
	list.forEach(function(item){

		if (!item || String(item.purchased) !== '1' || String(item.acknowledged) === '1' || !item.token){
			return
		}
		var token = String(item.token)
		get_ajax('google/purchase', {
			'cms_do': 'confirm',
			'purchase_token': token,
			'google_package_id': Array.isArray(item.products) && item.products.length ? String(item.products[0]) : '',
			'product_id': 0,
			'currency': 0,
			'success': function(confirmed){
				var body = payment_choice_body(confirmed)
				if (!body || String(body.ok) !== '1'){
					if (window.console){
						console.warn('payment choice: unconfirmed Play purchase not taken: ' + ((body && body.cms_error) || ''))
					}
					return
				}
				if (AppSplash.acknowledgePlayPurchase){
					AppSplash.acknowledgePlayPurchase(token)
				}
				if (String(body.changed) === '1' && $container && document.body.contains($container[0]) && typeof pricing_paid_refresh === 'function'){
					pricing_paid_refresh($container)
				}
			}
		}).catch(function(){})

	})

}

window.cms_hooks = window.cms_hooks || {}
window.cms_hooks.payment_choice = payment_choice_open

$(document).ready(function(){
	payment_choice_recover_init()
})
