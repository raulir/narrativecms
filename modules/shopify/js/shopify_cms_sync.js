function shopify_cms_sync_status_url(){

	var base = (typeof _cms_base !== 'undefined') ? _cms_base : '/'
	return base + 'cache/shopify_sync_status.txt?_=' + Date.now()

}

function shopify_cms_sync_set_status($root, text){

	$root.find('.shopify_cms_sync_status').text(text || '')

}

function shopify_cms_sync_parse_status(raw){

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

	if (!done && (display.indexOf(' - done') !== -1 || display.indexOf(' - stopped') !== -1)){
		done = true
	}

	return {
		'text': display,
		'done': done
	}

}

function shopify_cms_sync_poll($root){

	if (!$root.data('shopify_sync_waiting')){
		return
	}

	$.ajax({
		'url': shopify_cms_sync_status_url(),
		'method': 'GET',
		'dataType': 'text',
		'cache': false,
		'success': function(raw){

			if (!$root.data('shopify_sync_waiting')){
				return
			}

			var status = shopify_cms_sync_parse_status(raw)
			if (status.text){
				shopify_cms_sync_set_status($root, status.text)
			}

			if (status.done){
				shopify_cms_sync_finish($root, status.text || '')
			}

		},
		'error': function(){
			// Status file may not exist yet
		}
	})

}

function shopify_cms_sync_finish($root, text){

	var timer = $root.data('shopify_sync_timer')
	if (timer){
		clearInterval(timer)
		$root.removeData('shopify_sync_timer')
	}

	$root.data('shopify_sync_waiting', 0)
	$root.find('.shopify_cms_sync_button').removeClass('shopify_cms_sync_busy')

	if (text){
		shopify_cms_sync_set_status($root, text)
	}

}

function shopify_cms_sync_start($root){

	if ($root.data('shopify_sync_waiting')){
		return
	}

	$root.data('shopify_sync_waiting', 1)
	$root.find('.shopify_cms_sync_button').addClass('shopify_cms_sync_busy')
	shopify_cms_sync_set_status($root, 'Starting...')

	var timer = setInterval(function(){
		shopify_cms_sync_poll($root)
	}, 1000)
	$root.data('shopify_sync_timer', timer)

	setTimeout(function(){
		shopify_cms_sync_poll($root)
	}, 300)

	get_ajax_panel('shopify/shopify_cms_sync', {
		'do': 'sync_start',
		'no_html': '1'
	}, function(data){

		var result = (data && data.result) ? data.result : {}
		if (result.result && typeof result.result === 'object'){
			result = result.result
		}

		if (result.error === 'busy'){
			shopify_cms_sync_finish($root, 'Wait, sync is running')
			return
		}

		shopify_cms_sync_poll($root)
		if (result.text){
			shopify_cms_sync_finish($root, result.text)
		}

	})

}

function shopify_cms_sync_init($root){

	var $scope = $root ? $root.find('.shopify_cms_sync') : $('.shopify_cms_sync')

	$scope.not('.shopify_cms_sync_ok').each(function(){

		var $el = $(this)
		$el.addClass('shopify_cms_sync_ok')

		$el.find('.shopify_cms_sync_button').on('click.cms', function(){
			shopify_cms_sync_start($el)
		})

	})

}

function shopify_cms_sync_resize(){

}

$(document).ready(function(){

	$(window).on('resize.cms', shopify_cms_sync_resize)

	shopify_cms_sync_init()
	shopify_cms_sync_resize()

})
