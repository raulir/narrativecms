function product_box_variants($box){

	// jQuery .data() parses the JSON attribute.
	var variants = $box.data('variants')

	return Array.isArray(variants) ? variants : []

}

/**
 * Active picker values as { dim_id: value_id }. Ids only, compared as strings.
 */
function product_selected_dims($box){

	var selected = {}
	$box.find('.product_dim_active').each(function(){
		var dim_id = String($(this).data('dim_id') || '')
		if (dim_id){
			selected[dim_id] = String($(this).data('value_id'))
		}
	})

	return selected

}

/**
 * Picker cascade. Mirrors shop_dim_model::picker_state — keep both the same.
 *
 * Dims in page order. For dim k the candidates are available variants (offered and
 * sellable) that match the values selected in the dims before k. A value is available
 * when a candidate has it. A selection that is not available moves to the next
 * available value in list order (wrapping), or to none. Updates the active and
 * unavailable classes and returns the selected variant, or null.
 */
function product_picker_refresh($box){

	var selected = product_selected_dims($box)
	var candidates = $.grep(product_box_variants($box), function(variant){
		return !!variant.offered && !!variant.sellable
	})

	$box.find('.product_dim').each(function(){

		var $values = $(this).children('.product_dim_value')
		if (!$values.length){
			return
		}
		var dim_id = String($values.first().data('dim_id'))

		var have = {}
		$.each(candidates, function(_, variant){
			have[String((variant.dims || {})[dim_id] || '')] = true
		})

		var ids = $values.map(function(){
			return String($(this).data('value_id'))
		}).get()
		var start = ids.indexOf(selected[dim_id] || '')

		// Step 0 keeps the current value when it is still available.
		var pick = ''
		for (var step = 0; step < ids.length; step++){
			var i = start < 0 ? step : (start + step) % ids.length
			if (have[ids[i]]){
				pick = ids[i]
				break
			}
		}

		$values.each(function(i){
			$(this).toggleClass('product_dim_value_unavailable', !have[ids[i]])
			$(this).toggleClass('product_dim_active', pick !== '' && ids[i] === pick)
		})

		candidates = pick === '' ? [] : $.grep(candidates, function(variant){
			return String((variant.dims || {})[dim_id] || '') === pick
		})

	})

	return candidates.length ? candidates[0] : null

}

function product_apply_variant($box, variant){

	var $buy = $box.find('.product_buy')
	$box.find('.product_image_variant_active').removeClass('product_image_variant_active')
	$buy.removeClass('product_buy_disabled product_buy_off')

	// The cascade only returns available variants. None: Buy stays visible but disabled.
	if (!variant){
		$buy.addClass('product_buy_disabled')
		$buy.attr('data-product_item_id', '')
		var $fallback = $box.find('.product_image_novariant').first()
		if (!$fallback.length){
			$fallback = $box.find('.product_image').first()
		}
		$fallback.addClass('product_image_variant_active')
		return
	}

	$buy.attr('data-product_item_id', variant.product_item_id)
	if (variant.price_display){
		$box.find('.product_price').text(variant.price_display)
	}

	var $img = $box.find('.product_image_product_' + variant.product_item_id)
	if ($img.length){
		$img.addClass('product_image_variant_active')
	} else {
		$box.find('.product_image_novariant').addClass('product_image_variant_active')
	}

}

/**
 * Add-to-cart result under the Buy button: added message, or the error text from the cart reply.
 */
function product_show_add_result($box, body){

	var ok = !!(body && body.ok)
	$box.find('.product_added').toggleClass('product_added_visible', ok)
	$box.find('.product_add_error').text(ok ? '' : String((body && body.error) || ''))
		.toggleClass('product_add_error_visible', !ok)

}

function product_init(){

	$('.product_container').not('.product_container_ok').addClass('product_container_ok').each(function(){

		var $box = $(this)

		$box.on('click.cms', '.product_dim_value', function(){

			if ($(this).hasClass('product_dim_value_unavailable')){
				return
			}
			$(this).parent().children('.product_dim_value').removeClass('product_dim_active')
			$(this).addClass('product_dim_active')
			product_apply_variant($box, product_picker_refresh($box))

		})

		$box.on('click.cms', '.product_buy', function(){

			var $buy = $(this)
			if ($buy.hasClass('product_buy_disabled') || $buy.hasClass('product_buy_off')){
				return
			}

			var dims = product_selected_dims($box)

			var variant = product_picker_refresh($box) || {}
			var items = [{
				product_id: $box.attr('data-product_id') || 0,
				product_item_id: $buy.attr('data-product_item_id') || variant.product_item_id || 0,
				quantity: 1,
				dims: dims
			}]

			if (typeof cart_add_items === 'function'){
				cart_add_items(items).then(function(body){
					product_show_add_result($box, body)
				})
				return
			}

			// No cart panel on the page (no cart.js): post the add directly.
			get_ajax_panel('shop/cart', {
				'do': 'add',
				'product_id': items[0].product_id,
				'product_item_id': items[0].product_item_id,
				'quantity': 1,
				'dims': JSON.stringify(dims)
			}, function(result){
				product_show_add_result($box, result && result.result ? result.result : result)
			})

		})

	})

}

function product_resize(){

}

function product_scroll(){

}

$(document).ready(function(){

	product_init()
	product_resize()
	product_scroll()

})
