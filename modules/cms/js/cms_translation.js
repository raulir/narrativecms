/**
 * Use shared CMS top-of-page notifications (same as panel save).
 * hold_ms: milliseconds; 0 = stay until replaced; omit for default success/error timings.
 */
function cms_translation_notify(message, is_error, hold_ms){

	// cms_notification only creates when none exists — clear sticky / previous first
	$('.cms_notification_container').remove()

	var type = is_error ? 'error' : 'success'
	var ms = hold_ms
	if (ms === undefined || ms === null){
		ms = is_error ? 4000 : 2000
	}

	// cms_notification timer is in seconds; 0 / falsy = do not auto-hide
	var timer = ms > 0 ? (ms / 1000) : 0
	if (typeof cms_notification === 'function'){
		cms_notification(message, timer, type)
	} else if (is_error){
		alert(message)
	}

}

function cms_translation_show_saved($container){
	cms_translation_notify('Saved', false, 2000)
}

function cms_translation_show_message($container, message, is_error, hold_ms){
	cms_translation_notify(message, is_error, hold_ms)
}

function cms_translation_collect_values($container){

	var values = {}

	$container.find('.cms_translation_row').each(function(){

		var $row = $(this)
		var field_name = $row.attr('data-field_name') || $row.data('field_name') || ''
		if (!field_name){
			return
		}

		var $input = $row.find('.cms_translate_string_input').first()
		if (!$input.length){
			return
		}

		values[field_name] = $input.val()

	})

	return values

}

function cms_translation_save($container){

	if ($container.data('cms_translation_busy')){
		return
	}

	var cms_page_panel_id = parseInt($container.attr('data-cms_page_panel_id') || $container.data('cms_page_panel_id') || 0, 10)
	if (!cms_page_panel_id){
		return
	}

	var cms_language = $container.attr('data-cms_language') || $container.data('cms_language') || ''
	if (!cms_language && typeof cms_translate_string_get_cms_language === 'function'){
		cms_language = cms_translate_string_get_cms_language()
	}

	var values = cms_translation_collect_values($container)

	$container.data('cms_translation_busy', 1)
	$container.addClass('cms_translation_busy')

	get_ajax('cms/cms_translation', {
		'do': 'cms_translation_save',
		// Avoid ajax_api merging the target panel instance into the request
		'translation_cms_page_panel_id': cms_page_panel_id,
		'cms_language': cms_language,
		'values': JSON.stringify(values),
		'success': function(data){
			$container.data('cms_translation_busy', 0)
			$container.removeClass('cms_translation_busy')
			var res = data && data.result ? data.result : data
			if (res && res.error){
				cms_translation_show_message($container, res.error, true)
				return
			}
			cms_translation_show_saved($container)
		},
		'error': function(err){
			$container.data('cms_translation_busy', 0)
			$container.removeClass('cms_translation_busy')
			cms_translation_show_message($container, (err && err.message) ? err.message : 'Save failed', true)
		}
	})

}

function cms_translation_apply_suggestions($container, suggestions){

	if (!suggestions || typeof suggestions !== 'object'){
		return
	}

	$container.find('.cms_translation_row').each(function(){

		var $row = $(this)
		var field_name = $row.attr('data-field_name') || $row.data('field_name') || ''
		if (!field_name || typeof suggestions[field_name] === 'undefined'){
			return
		}

		var text = suggestions[field_name]
		if (text === null || typeof text === 'undefined'){
			text = ''
		}
		text = String(text)

		var $sug = $row.find('.cms_translation_ai_suggestion').first()
		$sug.text(text)
		$sug.attr('title', text)

		var $use = $row.find('.cms_translation_ai_use').first()
		if ($use.length){
			if (text !== ''){
				$use.show()
			} else {
				$use.hide()
			}
		}

	})

}

function cms_translation_ai($container){

	if ($container.data('cms_translation_busy')){
		return
	}

	var ask_confirm = $container.attr('data-ai_ask_confirmation')
	if (ask_confirm === undefined || ask_confirm === null || ask_confirm === ''){
		ask_confirm = $container.data('ai_ask_confirmation')
	}
	// Default Yes when unset; only skip when explicitly 0
	if (String(ask_confirm) !== '0'){
		var only_missing = String($container.attr('data-ai_only_missing') || $container.data('ai_only_missing') || '0') === '1'
		var msg = 'Request AI translations for this language? This calls the configured AI provider and may incur cost.'
		if (only_missing){
			msg = 'Request AI translations for missing texts only (empty, default, or same as base)? This calls the configured AI provider and may incur cost.'
		}
		if (!confirm(msg)){
			return
		}
	}

	var cms_page_panel_id = parseInt($container.attr('data-cms_page_panel_id') || $container.data('cms_page_panel_id') || 0, 10)
	if (!cms_page_panel_id){
		return
	}

	var cms_language = $container.attr('data-cms_language') || $container.data('cms_language') || ''
	if (!cms_language && typeof cms_translate_string_get_cms_language === 'function'){
		cms_language = cms_translate_string_get_cms_language()
	}

	$container.data('cms_translation_busy', 1)
	$container.addClass('cms_translation_busy cms_translation_ai_busy')
	var $ai_btn = $container.find('.cms_translation_ai')
	$ai_btn.addClass('cms_tool_button_disabled').data('cms_ai_label', $ai_btn.text()).text('…')
	// Hold until request finishes (success/error will replace this)
	cms_translation_show_message($container, 'AI working… can take up to a minute', false, 0)

	get_ajax('cms/cms_translation', {
		'do': 'cms_translation_ai',
		// Do not use cms_page_panel_id — ajax_api would load that panel into params
		'translation_cms_page_panel_id': cms_page_panel_id,
		'cms_language': cms_language,
		// Batch translate + xAI can exceed default limits
		'timeout': 180000,
		'success': function(data){
			$container.data('cms_translation_busy', 0)
			$container.removeClass('cms_translation_busy cms_translation_ai_busy')
			var label = $ai_btn.data('cms_ai_label') || 'AI'
			$ai_btn.removeClass('cms_tool_button_disabled').text(label)
			var res = data && data.result ? data.result : data
			if (res && res.error){
				cms_translation_show_message($container, res.error, true)
				return
			}
			cms_translation_apply_suggestions($container, res.suggestions || {})
			cms_translation_show_message($container, 'AI suggestions ready', false)
		},
		'error': function(err){
			$container.data('cms_translation_busy', 0)
			$container.removeClass('cms_translation_busy cms_translation_ai_busy')
			var label = $ai_btn.data('cms_ai_label') || 'AI'
			$ai_btn.removeClass('cms_tool_button_disabled').text(label)
			var msg = (err && err.message) ? err.message : 'AI request failed'
			if (err && err.status === 'timeout'){
				msg = 'AI request timed out (still may complete server-side). Try fewer fields or Only missing texts.'
			}
			cms_translation_show_message($container, msg, true)
		}
	})

}

function cms_translation_use_suggestion($row){

	var $sug = $row.find('.cms_translation_ai_suggestion').first()
	var text = $sug.text()
	var $input = $row.find('.cms_translate_string_input').first()
	if (!$input.length){
		return
	}
	$input.val(text).trigger('change')

}

function cms_translation_init($root){

	var $scope = $root ? $root.find('.cms_translation_container') : $('.cms_translation_container')

	$scope.not('.cms_translation_ok').each(function(){

		var $container = $(this)
		$container.addClass('cms_translation_ok')

		$container.find('.cms_translation_save').on('click.cms', function(){
			cms_translation_save($container)
		})

		$container.find('.cms_translation_ai').on('click.cms', function(){
			if ($(this).hasClass('cms_tool_button_disabled')){
				return
			}
			cms_translation_ai($container)
		})

		$container.on('click.cms', '.cms_translation_ai_use', function(){
			cms_translation_use_suggestion($(this).closest('.cms_translation_row'))
		})

		$container.on('click.cms', '.cms_translation_ai_suggestion', function(){
			var $row = $(this).closest('.cms_translation_row')
			if ($row.find('.cms_translation_ai_use').length){
				cms_translation_use_suggestion($row)
			}
		})

		if (typeof cms_translate_string_init_colour === 'function'){
			cms_translate_string_init_colour($container)
		}

	})

}

function cms_translation_resize(){

}

function cms_translation_scroll(){

}

$(document).ready(function(){

	$(window).on('resize.cms', cms_translation_resize)
	$(window).on('scroll.cms', cms_translation_scroll)

	cms_translation_init()
	cms_translation_resize()
	cms_translation_scroll()

})
