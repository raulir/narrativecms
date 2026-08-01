/**
 * Frontend Translate control in .cms_debug + opens user/page_translation popup.
 * Dropup opens on :hover (CSS), same pattern as basic/language.
 */

function page_translate_notify(message, is_error){

	if (typeof cms_notification === 'function'){
		cms_notification(message, is_error ? 4 : 2, is_error ? 'error' : 'success')
	} else if (is_error){
		alert(message)
	}

}

function page_translate_unit_context(){

	var unit_id = 0
	var types = []

	if (typeof engine_init_unit_id !== 'undefined' && engine_init_unit_id){
		unit_id = parseInt(engine_init_unit_id, 10) || 0
	}

	if (typeof music_set !== 'undefined' && Array.isArray(music_set)){
		var seen = {}
		music_set.forEach(function(el){
			var t = String((el && el.type) || '').toLowerCase()
			if (t && !seen[t]){
				seen[t] = 1
				types.push(t)
			}
		})
	}

	return {
		unit_id: unit_id,
		types: types
	}

}

function page_translate_close_dropdown($button){

	if ($button && $button.length){
		$button.addClass('page_translate_closed')
	}

}

function page_translate_render_list($button, items){

	var $list = $button.find('.page_translate_list')
	$list.empty()

	if (!items || !items.length){
		$list.append($('<div class="page_translate_option page_translate_empty"></div>').text('No translatable panels'))
		return
	}

	items.forEach(function(item){

		var label = item.label || ('#' + item.id)
		var kind = item.kind || ''
		var $opt = $('<div class="page_translate_option"></div>')
				.attr('data-id', item.id)
				.attr('data-panel_name', item.panel_name || '')
				.attr('data-kind', kind)
				.text(label)

		if (kind === 'settings'){
			$opt.addClass('page_translate_option_settings')
		} else if (kind === 'material'){
			$opt.addClass('page_translate_option_material')
		} else if (kind === 'product'){
			$opt.addClass('page_translate_option_product')
		}

		$list.append($opt)

	})

}

function page_translate_load_list($mount, $button, force){

	if ($button.data('page_translate_loading')){
		return
	}

	var cached = $button.data('page_translate_items')
	var ctx = page_translate_unit_context()
	var cache_key = String(ctx.unit_id) + '|' + ctx.types.join(',')

	if (!force && cached && $button.data('page_translate_cache_key') === cache_key){
		page_translate_render_list($button, cached)
		return
	}

	var cms_page_id = parseInt($mount.attr('data-cms_page_id') || $mount.data('cms_page_id') || 0, 10) || 0

	$button.data('page_translate_loading', 1)
	var $list_hint = $button.find('.page_translate_list')
	$list_hint.empty().append($('<div class="page_translate_option page_translate_empty"></div>').text('Loading…'))

	get_ajax('user/page_translate', {
		'do': 'page_translate_list',
		'cms_page_id': cms_page_id,
		'unit_id': ctx.unit_id,
		'types': JSON.stringify(ctx.types),
		'success': function(data){
			$button.data('page_translate_loading', 0)
			var res = (data && data.result) ? data.result : data
			var items = (res && res.items) ? res.items : []
			$button.data('page_translate_items', items)
			$button.data('page_translate_cache_key', cache_key)
			page_translate_render_list($button, items)
		},
		'error': function(){
			$button.data('page_translate_loading', 0)
			page_translate_render_list($button, [])
			page_translate_notify('Could not load panels', true)
		}
	})

}

function page_translate_open_panel($mount, panel_id){

	panel_id = parseInt(panel_id, 10) || 0
	if (panel_id < 1){
		return
	}

	var visitor_language = $mount.attr('data-visitor_language') || $mount.data('visitor_language') || ''

	if (typeof page_translation_open === 'function'){
		page_translation_open(panel_id, visitor_language)
		return
	}

	page_translate_notify('Translation UI not loaded', true)

}

function page_translate_build_control($mount){

	var $debug = $('.cms_debug').first()
	if (!$debug.length){
		var $footer = $('.footer_content, .hfooter_content').first()
		if ($footer.length){
			$debug = $('<div class="cms_debug"></div>')
			$footer.append($debug)
		} else {
			return
		}
	}

	if ($debug.find('.page_translate_button').length){
		return
	}

	var $button = $(
		'<div class="page_translate_button">' +
			'<div class="page_translate_button_label">Translate</div>' +
			'<div class="page_translate_list"></div>' +
		'</div>'
	)

	$debug.append($button)

	if (typeof cms_debug_order_buttons === 'function'){
		cms_debug_order_buttons()
	}

	// Hover open (CSS) — clear forced-closed + lazy-load list (language pattern)
	$button.on('mouseenter.page_translate', function(){
		$button.removeClass('page_translate_closed')
		page_translate_load_list($mount, $button, false)
	})

	$button.on('click.page_translate', '.page_translate_option', function(e){
		e.preventDefault()
		e.stopPropagation()
		var id = $(this).attr('data-id')
		if (!id || $(this).hasClass('page_translate_empty')){
			return
		}
		// Close list immediately (stay closed until re-hover)
		page_translate_close_dropdown($button)
		page_translate_open_panel($mount, id)
	})

}

function page_translate_move_engine_debug(){

	var $debug = $('.cms_debug').first()
	if (!$debug.length){
		return
	}

	var $btn = $('.engine_debug_button').first()
	if (!$btn.length){
		return
	}

	if ($btn.closest('.cms_debug').length){
		$btn.removeClass('engine_debug_button_hidden').show()
		return
	}

	$btn.detach().appendTo($debug)
	$btn.removeClass('engine_debug_button_hidden').show()

	if (typeof cms_debug_order_buttons === 'function'){
		cms_debug_order_buttons()
	}

}

function page_translate_init($root){

	var $scope = $root
		? $root.find('.page_translate_container').add($root.filter('.page_translate_container'))
		: $('.page_translate_container')

	$scope.not('.page_translate_ok').each(function(){

		var $mount = $(this)
		$mount.addClass('page_translate_ok')
		page_translate_build_control($mount)

	})

	page_translate_move_engine_debug()

}

$(document).ready(function(){

	page_translate_init()

	$(document).on('music_engine_ready.page_translate', function(){
		page_translate_move_engine_debug()
	})

})
