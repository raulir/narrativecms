function cms_images_unused_purge_status_url(){

	var base = (typeof _cms_base !== 'undefined') ? _cms_base : '/'
	return base + 'cache/cms_images_unused_purge_status.txt?_=' + Date.now()

}

function cms_images_unused_purge_set_status($root, text){

	$root.find('.cms_images_unused_purge_status').text(text || '')

}

function cms_images_unused_purge_params($root){

	var months = parseInt($root.find('.cms_images_unused_purge_months').val(), 10)
	if (isNaN(months) || months < 0){
		months = 0
	}

	// '' = all categories; '0' = no category; other = category name
	var category = $root.find('.cms_images_unused_purge_category').val()
	if (category === null || category === undefined){
		category = ''
	}

	return {
		'min_months': months,
		'category': category
	}

}

function cms_images_unused_purge_parse_status(raw){

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

function cms_images_unused_purge_poll($root){

	if (!$root.data('cms_images_unused_purge_waiting')){
		return
	}

	$.ajax({
		'url': cms_images_unused_purge_status_url(),
		'method': 'GET',
		'dataType': 'text',
		'cache': false,
		'success': function(raw){

			if (!$root.data('cms_images_unused_purge_waiting')){
				return
			}

			var status = cms_images_unused_purge_parse_status(raw)
			if (status.text){
				cms_images_unused_purge_set_status($root, status.text)
			}

			if (status.done){
				cms_images_unused_purge_finish($root, status.text || '')
			}

		},
		'error': function(){
			// File may not exist yet — keep waiting
		}
	})

}

function cms_images_unused_purge_finish($root, text){

	var timer = $root.data('cms_images_unused_purge_timer')
	if (timer){
		clearInterval(timer)
		$root.removeData('cms_images_unused_purge_timer')
	}

	$root.data('cms_images_unused_purge_waiting', 0)
	$root.find('.cms_images_unused_purge_button').removeClass('cms_images_unused_purge_busy')
	$root.find('.cms_images_unused_purge_test_button').removeClass('cms_images_unused_purge_busy')

	if (text){
		cms_images_unused_purge_set_status($root, text)
	}

}

function cms_images_unused_purge_test($root){

	if ($root.data('cms_images_unused_purge_waiting')){
		return
	}

	$root.find('.cms_images_unused_purge_test_button').addClass('cms_images_unused_purge_busy')
	cms_images_unused_purge_set_status($root, 'Testing...')

	var p = cms_images_unused_purge_params($root)

	get_ajax_panel('cms/cms_images_unused_purge', {
		'do': 'unused_purge_test',
		'min_months': p.min_months,
		'category': p.category,
		'no_html': '1'
	}, function(data){

		$root.find('.cms_images_unused_purge_test_button').removeClass('cms_images_unused_purge_busy')

		var result = (data && data.result) ? data.result : {}
		if (result.result && typeof result.result === 'object'){
			result = result.result
		}

		cms_images_unused_purge_set_status($root, result.text || 'No result')

	})

}

function cms_images_unused_purge_start($root){

	if ($root.data('cms_images_unused_purge_waiting')){
		return
	}

	$root.data('cms_images_unused_purge_waiting', 1)
	$root.find('.cms_images_unused_purge_button').addClass('cms_images_unused_purge_busy')
	$root.find('.cms_images_unused_purge_test_button').addClass('cms_images_unused_purge_busy')
	cms_images_unused_purge_set_status($root, 'Starting...')

	var timer = setInterval(function(){
		cms_images_unused_purge_poll($root)
	}, 1000)
	$root.data('cms_images_unused_purge_timer', timer)

	setTimeout(function(){
		cms_images_unused_purge_poll($root)
	}, 300)

	var p = cms_images_unused_purge_params($root)

	get_ajax_panel('cms/cms_images_unused_purge', {
		'do': 'unused_purge_start',
		'min_months': p.min_months,
		'category': p.category,
		'no_html': '1'
	}, function(data){

		var result = (data && data.result) ? data.result : {}
		if (result.result && typeof result.result === 'object'){
			result = result.result
		}

		if (result.error === 'busy'){
			cms_images_unused_purge_finish($root, 'Wait, image purge is running')
			return
		}

		cms_images_unused_purge_poll($root)
		if (result.text){
			cms_images_unused_purge_finish($root, result.text)
		}

	})

}

function cms_images_unused_purge_init($root){

	var $scope = $root ? $root.find('.cms_images_unused_purge') : $('.cms_images_unused_purge')
	if ($root && $root.hasClass('cms_images_unused_purge')){
		$scope = $scope.add($root)
	}

	$scope.not('.cms_images_unused_purge_ok').each(function(){

		var $el = $(this)
		$el.addClass('cms_images_unused_purge_ok')

		$el.find('.cms_images_unused_purge_test_button').on('click.cms', function(){
			cms_images_unused_purge_test($el)
		})

		$el.find('.cms_images_unused_purge_button').on('click.cms', function(){
			cms_images_unused_purge_start($el)
		})

	})

}

function cms_images_unused_purge_resize(){

}

$(document).ready(function(){

	$(window).on('resize.cms', cms_images_unused_purge_resize)

	cms_images_unused_purge_init()
	cms_images_unused_purge_resize()

})
