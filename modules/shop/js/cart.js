/**
 * Shop basic cart — local order draft only (no Storefront on add/open).
 * Checkout uses provides.shop_checkout panel when present (e.g. shopify/checkout).
 */

var cart_details_loading = false

function cart_panel_name(){
	return 'shop/cart'
}

function cart_set_badge(quantity){

	quantity = parseInt(quantity, 10) || 0

	var $container = $('.cart_container')
	$container.data('cart_quantity', quantity)
	$container.toggleClass('cart_empty', quantity < 1)

	// Desktop and mobile badge both carry .cart_quantity
	$container.find('.cart_quantity').html(quantity)

	var $header_count = $('.header_cart_count')
	if ($header_count.length){
		$header_count.text(quantity > 0 ? ' (' + quantity + ')' : '')
	}

}

function cart_close(){

	$('.cart_container').removeClass('cart_visible')
	get_ajax_panel(cart_panel_name(), {'cms_do': 'set_visible', 'value': '0'})

}

function cart_is_open(){
	return $('.cart_container').hasClass('cart_visible')
}

function cart_replace_html(html, keep_visible){

	var $host = $('.header_cart_panel')
	if (!$host.length){
		$host = $('.menu_cart_panel')
	}
	if (!$host.length){
		$host = $('.cart_container').parent()
	}
	if ($host.length && html){
		$host.html(html)
		if (keep_visible){
			$('.cart_container').addClass('cart_visible')
		}
		if (typeof cursor_init === 'function'){
			cursor_init()
		}
	}

}

/**
 * Full cart popup HTML from server (local lines).
 */
function cart_load_details(){

	if (cart_details_loading){
		return Promise.resolve(null)
	}
	cart_details_loading = true

	return new Promise(function(resolve){
		// do=open: counts as visitor activity, so the draft cart holds its stock again
		get_ajax_panel(cart_panel_name(), {
			'cms_do': 'open',
		}, function(result){
			cart_details_loading = false
			var html = result && result.result && (result.result.cms_html || result.result.html)
			if (html){
				var was_visible = cart_is_open()
				cart_replace_html(html, was_visible)
				var q = parseInt($('.cart_container').data('cart_quantity'), 10) || 0
				cart_set_badge(q)
			}
			resolve(result)
		})
	}).catch(function(){
		cart_details_loading = false
	})

}

/**
 * Add a line to the cart. items[0]: { product_id, product_item_id, quantity, dims: { dim_id: value_id } }.
 * Resolves { ok: 1, quantity } or { ok: 0, error } (error text from cart settings); the caller shows it.
 */
function cart_add_items(items, attributes){

	if (!items || !items.length){
		return Promise.resolve({'ok': 0, 'error': $('.cart_container').data('add_error')})
	}

	var item = items[0]
	var data = {
		'cms_do': 'add',
		'product_id': item.product_id || 0,
		'product_item_id': item.product_item_id || 0,
		'quantity': item.quantity || 1,
	}
	if (item.dims && typeof item.dims === 'object'){
		data.dims = JSON.stringify(item.dims)
	}

	if (attributes && typeof attributes === 'object'){
		data.attributes = JSON.stringify(attributes)
	}

	return new Promise(function(resolve){
		get_ajax_panel(cart_panel_name(), data, function(result){
			// panel_action may return raw json (ok/quantity) without full panel wrap
			var body = result
			if (result && result.result && result.result.ok !== undefined){
				body = result.result
			}
			if (body && body.ok){
				cart_set_badge(body.quantity || 0)
				if (cart_is_open()){
					cart_load_details().then(function(){
						resolve(body)
					})
					return
				}
				resolve(body)
				return
			}
			resolve({'ok': 0, 'error': (body && body.error) ? body.error : $('.cart_container').data('add_error')})
		})
	})

}

function cart_remove_line(line_id){

	return new Promise(function(resolve){
		get_ajax_panel(cart_panel_name(), {
			'cms_do': 'remove',
			'line_id': line_id,
			'cart_details': 1,
		}, function(result){
			var html = result && result.result && (result.result.cms_html || result.result.html)
			if (html){
				cart_replace_html(html, true)
				var q = parseInt($('.cart_container').data('cart_quantity'), 10) || 0
				cart_set_badge(q)
			}
			resolve(result)
		})
	})

}

/**
 * Status-only reconcile with checkout provider (e.g. Shopify cart gone after pay).
 * Never imports remote lines into the site cart.
 */
function cart_reconcile(){

	var $c = $('.cart_container')
	if (!$c.length){
		return
	}

	var panel = String($c.data('checkout_panel') || '')
	if (String($c.data('checkout_missing')) === '1' || !panel){
		return
	}

	// Only when there may be something to check
	if ((parseInt($c.data('cart_quantity'), 10) || 0) <= 0){
		return
	}

	get_ajax_panel(panel, {
		'cms_do': 'reconcile',
	}, function(result){
		var body = result
		if (result && result.result && (result.result.changed !== undefined || result.result.ok !== undefined)){
			body = result.result
		}
		if (body && body.changed){
			cart_set_badge(body.quantity || 0)
			if (cart_is_open()){
				cart_load_details()
			} else {
				// Refresh shell so empty state is correct
				get_ajax_panel(cart_panel_name(), {
					'cart_details': 0,
				}, function(r2){
					var html = r2 && r2.result && (r2.result.cms_html || r2.result.html)
					if (html){
						cart_replace_html(html, false)
						cart_set_badge(0)
					}
				})
			}
		}
	})

}

/**
 * Message under Checkout in the cart popup (hidden element from the template). Empty text hides it.
 */
function cart_show_message(text){

	$('.cart_container .cart_popup_message').text(String(text || ''))
		.toggleClass('cart_popup_message_visible', String(text || '') !== '')

}

function cart_checkout(){

	var $c = $('.cart_container')
	var panel = String($c.data('checkout_panel') || '')

	if (String($c.data('checkout_missing')) === '1' || !panel){
		cart_show_message($c.data('checkout_missing_error'))
		return
	}

	cart_show_message('')
	$c.addClass('cart_checkout_loading')

	// Server re-checks prices and stock first. Changed: show the updated cart and stop; next click goes on.
	get_ajax_panel(cart_panel_name(), {
		'cms_do': 'checkout_check',
	}, function(result){
		var html = result && result.result && (result.result.cms_html || result.result.html)
		var ready = $('<div>').html(html || '').find('.cart_container').data('checkout_ready')
		if (String(ready) === '1'){
			cart_checkout_provider(panel)
			return
		}
		$c.removeClass('cart_checkout_loading')
		if (html){
			cart_replace_html(html, true)
			cart_set_badge(parseInt($('.cart_container').data('cart_quantity'), 10) || 0)
		}
	})

}

function cart_checkout_provider(panel){

	var $c = $('.cart_container')

	get_ajax_panel(panel, {
		'cms_do': 'shop_checkout',
	}, function(result){
		$c.removeClass('cart_checkout_loading')
		var body = result
		if (result && result.result){
			if (result.result.redirect || result.result.ok !== undefined || result.result.changed !== undefined){
				body = result.result
			}
		}
		// Previous remote checkout finished while user was away
		// Previous remote checkout finished while user was away: show the emptied cart, then the provider's message.
		if (body && body.closed && body.changed){
			cart_set_badge(0)
			cart_load_details().then(function(){
				cart_show_message(body.error)
			})
			return
		}
		if (body && body.redirect){
			window.location.href = body.redirect
			return
		}
		cart_show_message((body && body.error) ? body.error : $c.data('checkout_error'))
	})

}

function cart_init($root){

	// Handlers are delegated on document (the cart HTML is replaced), so bind once: cart_ok on body.
	if ($('body').hasClass('cart_ok')){
		return
	}
	$('body').addClass('cart_ok')

	$(document).on('click.cms_cart', '.cart_popup_item_delete', function(){
		cart_remove_line($(this).data('line_id'))
	})

	$(document).on('click.cms_cart', '.cart_label', function(e){

		var $t = $(e.target)
		if ($t.closest('.cart_popup_item_delete,.cart_popup_checkout,.cart_popup_cancel,.cart_close').length){
			return
		}

		if (cart_is_open()){
			cart_close()
		} else {
			$('.cart_container').addClass('cart_visible')
			get_ajax_panel(cart_panel_name(), {'cms_do': 'set_visible', 'value': '1'})
			cart_load_details()
			cart_reconcile()
		}

	})

	$(document).on('click.cms_cart', '.cart_close,.cart_popup_cancel', function(e){
		e.preventDefault()
		e.stopPropagation()
		cart_close()
	})

	$(document).on('click.cms_cart', '.cart_popup', function(e){
		if ($(e.target).closest('.cart_popup_box').length){
			return
		}
		cart_close()
	})

	$(document).on('click.cms_cart', '.cart_popup_checkout', function(e){
		e.preventDefault()
		e.stopPropagation()
		cart_checkout()
	})

	if (cart_is_open()){
		cart_load_details()
	}

	// Status-only: remote cart completed → empty site cart
	cart_reconcile()

}

function cart_resize(){
}

function cart_scroll(){
}

$(document).ready(function(){

	$(window).on('resize.cms', cart_resize)
	$(window).on('scroll.cms', cart_scroll)
	cart_init()
	cart_resize()
	cart_scroll()

})
