function orders_admin_init(){

	var $box = $('.orders_admin_container').not('.orders_admin_ok')
	if (!$box.length || !$box.find('.orders_admin_rows').length){
		return
	}
	$box.addClass('orders_admin_ok')

	// state: one of view (filter button) or q (search); offset for the next page of rows
	var first = $box.find('.orders_admin_row').length
	var state = {'view': $box.data('view') || 'orders', 'q': '', 'offset': first, 'done': first < 50, 'loading': false}
	var timer = null

	function load(append){
		state.loading = true
		var params = {'offset': append ? state.offset : 0}
		if (state.q !== ''){
			params.q = state.q
		} else {
			params.view = state.view
		}
		get_ajax_panel('shop/orders_admin_rows', params).then(function(result){
			var html = (result && result.result && result.result.cms_html) || ''
			var count = $('<div>').html(html).find('.orders_admin_row').length
			if (append){
				$box.find('.orders_admin_rows').append(html)
				state.offset += count
			} else {
				$box.find('.orders_admin_rows').html(html)
				state.offset = count
			}
			state.done = count < 50
			sync_master()
			state.loading = false
		}).catch(function(){
			state.loading = false
		})
	}

	function set_active($button){
		$box.find('.orders_admin_view, .orders_admin_search_button').removeClass('cms_tool_button_active')
		$button.addClass('cms_tool_button_active')
	}

	function search(){
		var q = String($box.find('.orders_admin_q').val() || '').trim()
		if (q.length < 3){
			return
		}
		state.q = q
		set_active($box.find('.orders_admin_search_button'))
		load(false)
	}

	// Filter buttons clear the search
	$box.on('click.cms', '.orders_admin_view', function(){
		clearTimeout(timer)
		$box.find('.orders_admin_q').val('')
		state.q = ''
		state.view = $(this).data('view')
		set_active($(this))
		// The URL names the filter (?view=), so a reload or Back from an order opens the same one; no page load.
		window.history.replaceState(null, '', window.location.pathname + '?view=' + state.view)
		load(false)
	})

	// Search runs from 3 characters, a moment after typing stops
	$box.on('keyup.cms', '.orders_admin_q', function(){
		clearTimeout(timer)
		timer = setTimeout(search, 300)
	})

	$box.on('click.cms', '.orders_admin_search_button', search)

	// Tick boxes select rows (CMS list look: [ ] / [v]); the toolbar tick selects all shown rows.
	function sync_master(){
		var $rows = $box.find('.orders_admin_row')
		var all = $rows.length > 0 && $rows.filter('.cms_list_list_selected').length === $rows.length
		$box.find('.cms_list_toolbar_tick').text(all ? '[v]' : '[ ]')
	}

	$box.on('click.cms', '.orders_admin_row .cms_list_list_tick', function(){
		var $row = $(this).closest('.orders_admin_row')
		var on = !$row.hasClass('cms_list_list_selected')
		$row.toggleClass('cms_list_list_selected', on)
		$(this).text(on ? '[v]' : '[ ]')
		sync_master()
	})

	$box.on('click.cms', '.cms_list_toolbar_tick', function(){
		var on = $(this).text() !== '[v]'
		$box.find('.orders_admin_row').toggleClass('cms_list_list_selected', on).find('.cms_list_list_tick').text(on ? '[v]' : '[ ]')
		sync_master()
	})

	$(window).on('scroll.orders_admin', function(){
		if (state.done || state.loading){
			return
		}
		if ($(window).scrollTop() + $(window).height() < $(document).height() - 200){
			return
		}
		load(true)
	})

}

function orders_admin_resize(){

}

function orders_admin_scroll(){

}

$(document).ready(function(){
	orders_admin_init()
})
