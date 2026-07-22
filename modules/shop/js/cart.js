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
	$container.attr('data-cart_quantity', quantity)
	$container.data('cart_quantity', quantity)

	if (quantity > 0){
		$container.removeClass('cart_empty')
	} else {
		$container.addClass('cart_empty')
	}

	var $num = $container.find('.cart_quantity').first()
	if ($num.length){
		$num.html(quantity)
	}

}

function cart_is_open(){
	return $('.cart_container').hasClass('cart_visible')
}

function cart_replace_html(html, keep_visible){

	var $host = $('.menu_cart_panel')
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
		get_ajax_panel(cart_panel_name(), {
			'cart_details': 1,
		}, function(result){
			cart_details_loading = false
			var html = result && result.result && (result.result._html || result.result.html)
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
 * Add line(s) to local shop cart. items: { product_id, shopify_variant_id|merchandiseId, quantity, expectedPrice }
 * attributes: key/value object for customisation.
 */
function cart_add_items(items, attributes){

	if (!items || !items.length){
		return Promise.resolve(null)
	}

	var item = items[0]
	var merchandise = item.merchandiseId || item.merchandise_id || ''
	var variant = item.shopify_variant_id || ''
	if (!variant && merchandise && merchandise.indexOf('gid://') === 0){
		var parts = merchandise.split('/')
		variant = parts[parts.length - 1]
	}
	if (!merchandise && variant){
		merchandise = variant.indexOf('gid://') === 0 ? variant : ('gid://shopify/ProductVariant/' + variant)
	}

	var data = {
		'do': 'add',
		'product_id': item.product_id || $('.product_container').data('product_id') || 0,
		'shopify_variant_id': variant,
		'merchandise_id': merchandise,
		'quantity': item.quantity || 1,
		'expected_price': item.expectedPrice != null ? item.expectedPrice : (item.expected_price || ''),
		'item': item.item || item.heading || '',
		'image': item.image || '',
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
			var err = (body && body.error) ? body.error : 'Could not add to cart'
			if (typeof window !== 'undefined' && window.alert){
				window.alert(err)
			}
			resolve(null)
		})
	})

}

function cart_remove_line(line_id){

	return new Promise(function(resolve){
		get_ajax_panel(cart_panel_name(), {
			'do': 'remove',
			'item_id': line_id,
			'cart_details': 1,
		}, function(result){
			var html = result && result.result && (result.result._html || result.result.html)
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

	var panel = ($c.attr('data-checkout_panel') || $c.data('checkout_panel') || '').toString().trim()
	var missing = String($c.attr('data-checkout_missing') || $c.data('checkout_missing') || '') === '1'
	if (missing || !panel){
		return
	}

	// Only when there may be something to check
	var qty = parseInt($c.attr('data-cart_quantity') || $c.data('cart_quantity') || 0, 10) || 0
	if (qty <= 0){
		return
	}

	get_ajax_panel(panel, {
		'do': 'reconcile',
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
					var html = r2 && r2.result && (r2.result._html || r2.result.html)
					if (html){
						cart_replace_html(html, false)
						cart_set_badge(0)
					}
				})
			}
		}
	})

}

function cart_checkout(){

	var $c = $('.cart_container')
	var panel = ($c.attr('data-checkout_panel') || $c.data('checkout_panel') || '').toString().trim()
	var missing = String($c.attr('data-checkout_missing') || $c.data('checkout_missing') || '') === '1'

	if (missing || !panel){
		var $err = $c.find('.cart_checkout_error')
		if (!$err.length){
			$c.find('.cart_popup_checkout').after(
				'<div class="cart_checkout_error">Select shop checkout provider!</div>'
			)
		}
		return
	}

	$c.addClass('cart_checkout_loading')

	get_ajax_panel(panel, {
		'do': 'shop_checkout',
	}, function(result){
		$c.removeClass('cart_checkout_loading')
		var body = result
		if (result && result.result){
			if (result.result.redirect || result.result.ok !== undefined || result.result.changed !== undefined){
				body = result.result
			}
		}
		// Previous remote checkout finished while user was away
		if (body && body.closed && body.changed){
			cart_set_badge(0)
			if (typeof window !== 'undefined' && window.alert && body.error){
				window.alert(body.error)
			}
			cart_load_details()
			return
		}
		if (body && body.redirect){
			window.location.href = body.redirect
			return
		}
		var err = (body && body.error) ? body.error : 'Checkout is not available'
		if (typeof window !== 'undefined' && window.alert){
			window.alert(err)
		}
	})

}

function cart_init($root){

	// Allow re-init after HTML replace without double-binding: document delegation once
	if ($('body').data('shop_cart_bound')){
		return
	}
	$('body').data('shop_cart_bound', 1)

	$(document).on('click.cms_cart', '.cart_popup_item_delete', function(){
		cart_remove_line($(this).data('item_id'))
	})

	$(document).on('click.cms_cart', '.cart_label,.cart_close', function(e){

		var $t = $(e.target)
		if ($t.closest('.cart_popup_item_delete,.cart_popup_checkout').length){
			return
		}

		if (cart_is_open()){
			$('.cart_container').removeClass('cart_visible')
			get_ajax_panel(cart_panel_name(), {'do': 'set_visible', 'value': '0'})
		} else {
			$('.cart_container').addClass('cart_visible')
			get_ajax_panel(cart_panel_name(), {'do': 'set_visible', 'value': '1'})
			cart_load_details()
			cart_reconcile()
		}

	})

	$(document).on('click.cms_cart', '.cart_popup_checkout', function(){
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
