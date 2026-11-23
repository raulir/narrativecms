function order_admin_init(){

	var $box = $('.order_admin_container').not('.order_admin_ok')
	if (!$box.length || !$box.data('order_id')){
		return
	}
	$box.addClass('order_admin_ok')

	// The action runs, then the panel comes back fresh and replaces the page body in place.
	function save(data){
		data.order_id = $box.data('order_id')
		$box.addClass('order_admin_busy')
		get_ajax_panel('shop/order_admin', data).then(function(result){
			var html = (result && result.result && result.result.cms_html) || ''
			if (html === ''){
				$box.removeClass('order_admin_busy')
				return
			}
			$box.replaceWith(html)
			order_admin_init()
		}).catch(function(){
			$box.removeClass('order_admin_busy')
		})
	}

	// Email the buyer + Tracking number: only while the select is Fulfilled on an order not Fulfilled yet
	var $dispatch = $box.find('.order_admin_dispatch')
	function dispatch_show(){
		var on = $box.find('.order_admin_status').val() === 'fulfilled' && $dispatch.data('status') !== 'fulfilled'
		$dispatch.toggleClass('order_admin_dispatch_on', on)
		return on
	}
	// Cancelled on a paid order: a note that the refund is made in Stripe (the block exists only on paid orders)
	function cancel_note_show(){
		$box.find('.order_admin_cancel_note').toggleClass('order_admin_cancel_note_on', $box.find('.order_admin_status').val() === 'cancelled')
	}

	$box.on('change.cms', '.order_admin_status', function(){
		dispatch_show()
		cancel_note_show()
	})
	dispatch_show()
	cancel_note_show()

	$box.on('click.cms', '.order_admin_return', function(){
		save({
			'cms_do': 'return_to_stock',
			'line_id': $(this).data('line_id')
		})
	})

	$box.on('click.cms', '.order_admin_email_buyer', function(){
		var on = $(this).data('on') ? 0 : 1
		$(this).data('on', on).find('.order_admin_email_tick').text(on ? '[v]' : '[ ]')
	})

	$box.on('click.cms', '.order_admin_save_status', function(){
		// A finished order has no order status select: only the payment status is sent
		var data = {
			'cms_do': 'save_status',
			'payment_status': $box.find('.order_admin_payment_status').val() || '',
			'status': $box.find('.order_admin_status').val() || ''
		}
		if (dispatch_show()){
			data.tracking_number = String($box.find('.order_admin_tracking').val() || '').trim()
			data.email_buyer = $box.find('.order_admin_email_buyer').data('on') ? '1' : '0'
		}
		save(data)
	})

	$box.on('click.cms', '.order_admin_post', function(){
		var comment = String($box.find('.order_admin_comment').val() || '').trim()
		if (comment === ''){
			return
		}
		save({
			'cms_do': 'add_comment',
			'comment': comment
		})
	})

}


$(document).ready(function(){
	order_admin_init()
})
