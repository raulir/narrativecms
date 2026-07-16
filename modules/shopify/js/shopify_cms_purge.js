function shopify_cms_purge_status_url(){

	var base = (typeof _cms_base !== 'undefined') ? _cms_base : '/'
	return base + 'cache/shopify_purge_status.txt?_=' + Date.now()

}

function shopify_cms_purge_set_status($root, text){

	$root.find('.shopify_cms_purge_status').text(text || '')

}

function shopify_cms_purge_parse_status(raw){

	raw = (raw || '').toString()
	var lines = raw.replace(/^\uFEFF/, '').trim().split(/\r\n|\n|\r/)
	var display = lines[0] || ''
	var done = false

	for (var i = 0; i < lines.length; i++){
		if (String(lines[i] || '').trim() === 'done'){
			done = true
			break
		}
	}

	// Also treat finished suffix lines as done (in case done marker missing)
	if (!done && (display.indexOf(' - done') !== -1 || display.indexOf(' - stopped') !== -1)){
		done = true
	}

	return {
		'text': display,
		'done': done
	}

}

function shopify_cms_purge_poll($root){

	if (!$root.data('shopify_purge_waiting')){
		return
	}

	// Read plain status file from cache — no PHP/session (does not block on purge_start)
	$.ajax({
		'url': shopify_cms_purge_status_url(),
		'method': 'GET',
		'dataType': 'text',
		'cache': false,
		'success': function(raw){

			if (!$root.data('shopify_purge_waiting')){
				return
			}

			var status = shopify_cms_purge_parse_status(raw)
			if (status.text){
				shopify_cms_purge_set_status($root, status.text)
			}

			if (status.done){
				shopify_cms_purge_finish($root, status.text || '')
			}

		},
		'error': function(){
			// File may not exist yet right after click — keep waiting
		}
	})

}

function shopify_cms_purge_finish($root, text){

	var timer = $root.data('shopify_purge_timer')
	if (timer){
		clearInterval(timer)
		$root.removeData('shopify_purge_timer')
	}

	$root.data('shopify_purge_waiting', 0)
	$root.find('.shopify_cms_purge_button').removeClass('shopify_cms_purge_busy')

	if (text){
		shopify_cms_purge_set_status($root, text)
	}

}

function shopify_cms_purge_start($root){

	if ($root.data('shopify_purge_waiting')){
		return
	}

	$root.data('shopify_purge_waiting', 1)
	$root.find('.shopify_cms_purge_button').addClass('shopify_cms_purge_busy')
	shopify_cms_purge_set_status($root, 'Starting...')

	var timer = setInterval(function(){
		shopify_cms_purge_poll($root)
	}, 1000)
	$root.data('shopify_purge_timer', timer)

	setTimeout(function(){
		shopify_cms_purge_poll($root)
	}, 300)

	// Long-running worker (session closed server-side so it does not lock other requests)
	get_ajax_panel('shopify/shopify_cms_purge', {
		'do': 'purge_start',
		'no_html': '1'
	}, function(data){

		var result = (data && data.result) ? data.result : {}
		if (result.result && typeof result.result === 'object'){
			result = result.result
		}

		if (result.error === 'busy'){
			shopify_cms_purge_finish($root, 'Wait, purge is running')
			return
		}

		// Prefer live file status; use ajax payload as final fallback
		shopify_cms_purge_poll($root)
		if (result.text){
			shopify_cms_purge_finish($root, result.text)
		}

	})

}

function shopify_cms_purge_init($root){

	var $scope = $root ? $root.find('.shopify_cms_purge') : $('.shopify_cms_purge')

	$scope.not('.shopify_cms_purge_ok').each(function(){

		var $el = $(this)
		$el.addClass('shopify_cms_purge_ok')

		$el.find('.shopify_cms_purge_button').on('click.cms', function(){
			shopify_cms_purge_start($el)
		})

	})

}

function shopify_cms_purge_resize(){

}

$(document).ready(function(){

	$(window).on('resize.cms', shopify_cms_purge_resize)

	shopify_cms_purge_init()
	shopify_cms_purge_resize()

})
