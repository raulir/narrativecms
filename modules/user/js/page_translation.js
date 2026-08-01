/**
 * Frontend translation grid popup (user/page_translation).
 * Self-contained overlay — does not use cms_popup or admin CMS chrome.
 */

function page_translation_notify($container, message, is_error, hold_ms){

	var ms = hold_ms
	if (ms === undefined || ms === null){
		ms = is_error ? 4000 : 2000
	}

	if (!$container || !$container.length){
		if (is_error){
			alert(message)
		}
		return
	}

	var $bar = $container.find('.page_translation_toolbar').first()
	if (!$bar.length){
		if (is_error){
			alert(message)
		}
		return
	}

	$bar.find('.page_translation_note').remove()
	var $note = $('<div class="page_translation_note"></div>').text(message)
	if (is_error){
		$note.addClass('page_translation_note_error')
	}
	$bar.append($note)
	if (ms > 0){
		setTimeout(function(){
			$note.fadeOut(200, function(){ $note.remove() })
		}, ms)
	}

}

function page_translation_close(){

	var $overlay = $('.page_translation_overlay')
	if (!$overlay.length){
		return
	}
	$overlay.css({'opacity': '0'})
	setTimeout(function(){
		$overlay.remove()
	}, 200)

}

function page_translation_collect_values($container){

	var values = {}

	$container.find('.page_translation_row').each(function(){

		var $row = $(this)
		var field_name = $row.attr('data-field_name') || ''
		if (!field_name){
			return
		}

		var $input = $row.find('.page_translation_input').first()
		if (!$input.length){
			return
		}

		values[field_name] = $input.val()

	})

	return values

}

function page_translation_save($container){

	if ($container.data('page_translation_busy')){
		return
	}

	var cms_page_panel_id = parseInt($container.attr('data-cms_page_panel_id') || 0, 10)
	if (!cms_page_panel_id){
		return
	}

	var cms_language = $container.attr('data-cms_language') || ''
	var values = page_translation_collect_values($container)

	$container.data('page_translation_busy', 1)
	$container.addClass('page_translation_busy')

	get_ajax('user/page_translation', {
		'do': 'page_translation_save',
		'translation_cms_page_panel_id': cms_page_panel_id,
		'cms_language': cms_language,
		'values': JSON.stringify(values),
		'success': function(data){
			$container.data('page_translation_busy', 0)
			$container.removeClass('page_translation_busy')
			var res = data && data.result ? data.result : data
			if (res && res.error){
				page_translation_notify($container, res.error, true)
				return
			}
			page_translation_notify($container, 'Saved', false)
		},
		'error': function(err){
			$container.data('page_translation_busy', 0)
			$container.removeClass('page_translation_busy')
			page_translation_notify($container, (err && err.message) ? err.message : 'Save failed', true)
		}
	})

}

function page_translation_apply_suggestions($container, suggestions){

	if (!suggestions || typeof suggestions !== 'object'){
		return
	}

	$container.find('.page_translation_row').each(function(){

		var $row = $(this)
		var field_name = $row.attr('data-field_name') || ''
		if (!field_name || typeof suggestions[field_name] === 'undefined'){
			return
		}

		var text = suggestions[field_name]
		if (text === null || typeof text === 'undefined'){
			text = ''
		}
		text = String(text)

		var $sug = $row.find('.page_translation_ai_suggestion').first()
		$sug.text(text)
		$sug.attr('title', text)

		var $use = $row.find('.page_translation_ai_use').first()
		if ($use.length){
			if (text !== ''){
				$use.show()
			} else {
				$use.hide()
			}
		}

	})

}

function page_translation_ai($container){

	if ($container.data('page_translation_busy')){
		return
	}

	var ask_confirm = $container.attr('data-ai_ask_confirmation')
	if (ask_confirm === undefined || ask_confirm === null || ask_confirm === ''){
		ask_confirm = $container.data('ai_ask_confirmation')
	}
	if (String(ask_confirm) !== '0'){
		var only_missing = String($container.attr('data-ai_only_missing') || '0') === '1'
		var msg = 'Request AI translations for this language? This calls the configured AI provider and may incur cost.'
		if (only_missing){
			msg = 'Request AI translations for missing texts only? This calls the configured AI provider and may incur cost.'
		}
		if (!confirm(msg)){
			return
		}
	}

	var cms_page_panel_id = parseInt($container.attr('data-cms_page_panel_id') || 0, 10)
	if (!cms_page_panel_id){
		return
	}

	var cms_language = $container.attr('data-cms_language') || ''
	// Pass live editor values so emptied fields are offered AI without saving first
	var values = page_translation_collect_values($container)

	$container.data('page_translation_busy', 1)
	$container.addClass('page_translation_busy page_translation_ai_busy')
	var $ai_btn = $container.find('.page_translation_ai')
	$ai_btn.addClass('page_translation_btn_disabled').data('page_ai_label', $ai_btn.text()).text('…')
	page_translation_notify($container, 'AI working… can take up to a minute', false, 0)

	get_ajax('user/page_translation', {
		'do': 'page_translation_ai',
		'translation_cms_page_panel_id': cms_page_panel_id,
		'cms_language': cms_language,
		'values': JSON.stringify(values),
		'timeout': 180000,
		'success': function(data){
			$container.data('page_translation_busy', 0)
			$container.removeClass('page_translation_busy page_translation_ai_busy')
			var label = $ai_btn.data('page_ai_label') || 'AI'
			$ai_btn.removeClass('page_translation_btn_disabled').text(label)
			var res = data && data.result ? data.result : data
			if (res && res.error){
				page_translation_notify($container, res.error, true)
				return
			}
			page_translation_apply_suggestions($container, res.suggestions || {})
			page_translation_notify($container, 'AI suggestions ready', false)
		},
		'error': function(err){
			$container.data('page_translation_busy', 0)
			$container.removeClass('page_translation_busy page_translation_ai_busy')
			var label = $ai_btn.data('page_ai_label') || 'AI'
			$ai_btn.removeClass('page_translation_btn_disabled').text(label)
			var msg = (err && err.message) ? err.message : 'AI request failed'
			if (err && err.status === 'timeout'){
				msg = 'AI request timed out. Try fewer fields or Only missing texts.'
			}
			page_translation_notify($container, msg, true)
		}
	})

}

function page_translation_set_edit_value($row, text){

	text = String(text || '').trim()
	if (text === ''){
		return
	}
	var $input = $row.find('.page_translation_input').first()
	if (!$input.length){
		return
	}
	$input.val(text).trigger('change')

}

function page_translation_bind($container){

	if (!$container || !$container.length || $container.hasClass('page_translation_ok')){
		return
	}

	$container.addClass('page_translation_ok')

	$container.find('.page_translation_save').on('click.page_translation', function(){
		page_translation_save($container)
	})

	$container.find('.page_translation_cancel').on('click.page_translation', function(){
		page_translation_close()
	})

	$container.find('.page_translation_ai').on('click.page_translation', function(){
		if ($(this).hasClass('page_translation_btn_disabled')){
			return
		}
		page_translation_ai($container)
	})

	$container.on('click.page_translation', '.page_translation_ai_use', function(){
		page_translation_set_edit_value(
			$(this).closest('.page_translation_row'),
			$(this).closest('.page_translation_row').find('.page_translation_ai_suggestion').text()
		)
	})

	$container.on('click.page_translation', '.page_translation_default_use', function(){
		page_translation_set_edit_value(
			$(this).closest('.page_translation_row'),
			$(this).closest('.page_translation_row').find('.page_translation_default_text').text()
		)
	})

	$container.on('click.page_translation', '.page_translation_ai_suggestion', function(){
		var $row = $(this).closest('.page_translation_row')
		if ($row.find('.page_translation_ai_use:visible').length){
			page_translation_set_edit_value($row, $(this).text())
		}
	})

	$container.on('click.page_translation', '.page_translation_default_text', function(){
		var $row = $(this).closest('.page_translation_row')
		if ($row.find('.page_translation_default_use:visible').length){
			page_translation_set_edit_value($row, $(this).text())
		}
	})

}

function page_translation_open(panel_id, visitor_language){

	panel_id = parseInt(panel_id, 10) || 0
	if (panel_id < 1){
		return
	}

	// Replace existing overlay
	$('.page_translation_overlay').remove()

	var $overlay = $(
		'<div class="page_translation_overlay">' +
			'<div class="page_translation_dialog">' +
				'<div class="page_translation_dialog_inner">Loading…</div>' +
			'</div>' +
		'</div>'
	)

	$('body').append($overlay)

	setTimeout(function(){
		$overlay.addClass('page_translation_overlay_open')
	}, 20)

	// Click dimmed backdrop to cancel
	$overlay.on('click.page_translation', function(e){
		if (e.target === $overlay[0]){
			page_translation_close()
		}
	})

	get_ajax_panel('user/page_translation', {
		'cms_page_panel_id': panel_id,
		'cms_language': visitor_language || ''
	}, function(data){

		var html = ''
		if (data && data.result && data.result._html){
			html = data.result._html
		} else if (data && data._html){
			html = data._html
		}

		var $inner = $overlay.find('.page_translation_dialog_inner')
		$inner.html(html || 'Error loading translation')

		var $container = $inner.find('.page_translation_container').first()
		page_translation_bind($container)

	})

}

function page_translation_init($root){

	var $scope = $root
		? $root.find('.page_translation_container').add($root.filter('.page_translation_container'))
		: $('.page_translation_container')

	$scope.each(function(){
		page_translation_bind($(this))
	})

}

$(document).ready(function(){
	page_translation_init()
})
