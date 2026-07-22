var search_request_gen = 0
var search_debounce_timer = null

function search_debounce_ms($root){

	var s = parseFloat($root.data('debounce_s'), 10)
	if (isNaN(s) || s < 0){
		s = 0.5
	}
	return Math.round(s * 1000)

}

function search_clear_results($root){

	var $results = $root.find('.search_results')
	$results.removeClass('search_results_active').html('')
	$root.data('lastterm', '')

}

function search_run($root, term){

	term = String(term || '').trim()
	var min_chars = parseInt($root.data('min_chars'), 10)
	if (isNaN(min_chars) || min_chars < 1){
		min_chars = 3
	}

	if (term === ''){
		search_clear_results($root)
		return
	}

	if (term === $root.data('lastterm')){
		return
	}

	var gen = ++search_request_gen

	get_ajax_panel('search/searchajax', {
		'term': term
	}, function(data){

		if (gen !== search_request_gen){
			return
		}

		var html = ''
		if (data && data.result){
			html = data.result._html || data.result.html || ''
		}

		$root.data('lastterm', term)
		var $results = $root.find('.search_results')
		if (html){
			$results.html(html).addClass('search_results_active')
		} else {
			$results.removeClass('search_results_active').html('')
		}

		if (typeof cursor_init === 'function'){
			cursor_init()
		}

	})

}

function search_schedule($input){

	var $root = $input.closest('.search_container')
	if (!$root.length){
		return
	}

	if (search_debounce_timer){
		clearTimeout(search_debounce_timer)
		search_debounce_timer = null
	}

	var term = String($input.val() || '')
	if (String(term).trim() === ''){
		search_clear_results($root)
		return
	}

	var ms = search_debounce_ms($root)
	search_debounce_timer = setTimeout(function(){
		search_debounce_timer = null
		search_run($root, $input.val())
	}, ms)

}

function search_init(){

	// Delegated: search/search is often injected via ajax into the modal
	$(document).off('input.cms_search keyup.cms_search change.cms_search', '.search_input')
	$(document).on('input.cms_search keyup.cms_search change.cms_search', '.search_input', function(){
		search_schedule($(this))
	})

}

function search_resize(){

}

function search_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		search_resize();
	});
	
	$(window).on('scroll.cms', function(){
		search_scroll();
	});
	
	search_init();

	search_resize();
	
	search_scroll();

});
