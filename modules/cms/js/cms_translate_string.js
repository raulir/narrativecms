var cms_translate_string_origin = null
var cms_translate_string_origin_class = 'cms_translate_string_origin'

function cms_translate_string_is_readonly_parent($icon){

	var $input = $icon.closest('.cms_input_text, .cms_input_textarea, .cms_input_colour')

	return $input.hasClass('cms_input_text_readonly')
		|| $input.hasClass('cms_input_textarea_readonly')
		|| $input.length === 0

}

function cms_translate_string_get_field_context($icon){

	var $input_wrap = $icon.closest('.cms_input_text, .cms_input_textarea, .cms_input_colour')
	var field_type = $icon.data('field_type') || 'text'
	var $field = $()

	if ($input_wrap.hasClass('cms_input_textarea')){
		field_type = 'textarea'
		$field = $('textarea', $input_wrap).first()
	} else if ($input_wrap.hasClass('cms_input_colour')){
		field_type = 'colour'
		$field = $('.cms_input_colour_input', $input_wrap).first()
	} else {
		$field = $('.cms_input_text_input', $input_wrap).first()
		if (!$field.length){
			$field = $('input', $input_wrap).not('.cms_input_colour_helper').first()
		}
	}

	return {
		$field: $field,
		$input_wrap: $input_wrap,
		field_name: $field.attr('name') || '',
		field_type: field_type,
	}

}

function cms_translate_string_clear_origin_mark(){

	$('.' + cms_translate_string_origin_class).removeClass(cms_translate_string_origin_class)

}

function cms_translate_string_mark_origin($icon){

	cms_translate_string_clear_origin_mark()

	if ($icon && $icon.length){
		$icon.addClass(cms_translate_string_origin_class)
	}

}

function cms_translate_string_get_marked_context(){

	var $icon = $('.' + cms_translate_string_origin_class).first()

	if (!$icon.length){
		return null
	}

	return cms_translate_string_get_field_context($icon)

}

function cms_translate_string_normalise_language_id(language_id){

	return (language_id || '').toString().toLowerCase()

}

function cms_translate_string_value_for_language(values, language_id){

	if (!values || !language_id){
		return undefined
	}

	if (typeof values[language_id] !== 'undefined'){
		return values[language_id]
	}

	var normalised = cms_translate_string_normalise_language_id(language_id)

	for (var key in values){
		if (!values.hasOwnProperty(key)){
			continue
		}
		if (cms_translate_string_normalise_language_id(key) === normalised){
			return values[key]
		}
	}

	return undefined

}

function cms_translate_string_get_cms_language(){

	var $current = $('.cms_language_select_current').first()
	if (!$current.length){
		return ''
	}

	return $current.attr('data-language') || $current.data('language') || ''

}

function cms_translate_string_popup_value_for_language($container, language_id){

	if (!$container || !$container.length || !language_id){
		return undefined
	}

	var normalised = cms_translate_string_normalise_language_id(language_id)
	var value = undefined

	$container.find('.cms_translate_string_input').each(function(){
		var row_language = $(this).attr('data-language_id') || $(this).data('language_id') || ''
		if (cms_translate_string_normalise_language_id(row_language) === normalised){
			value = $(this).val()
			return false
		}
	})

	return value

}

function cms_translate_string_find_tinymce_editor($field){

	if (!$field.length){
		return null
	}

	var tinymce_api = window.tinymce || window.tinyMCE
	if (!tinymce_api){
		return null
	}

	var field_id = $field.attr('id')
	if (field_id && typeof tinymce_api.get === 'function'){
		var editor_by_id = tinymce_api.get(field_id)
		if (editor_by_id){
			return editor_by_id
		}
	}

	var editors = tinymce_api.editors || []
	for (var i = 0; i < editors.length; i++){
		var ed = editors[i]
		var el = ed.targetElm || (typeof ed.getElement === 'function' ? ed.getElement() : null)
		if (el && el === $field[0]){
			return ed
		}
	}

	return null

}

function cms_translate_string_set_field_value($field, value, field_type, $input_wrap){

	if (!$field.length){
		return
	}

	if ($field.hasClass('cms_tinymce')){
		var editor = cms_translate_string_find_tinymce_editor($field)
		if (editor){
			editor.setContent(value)
			if (typeof editor.save === 'function'){
				editor.save()
			}
			return
		}
	}

	$field.val(value).trigger('change').trigger('input')

	if (field_type === 'colour' && typeof cms_input_colour_update === 'function'){
		cms_input_colour_update($input_wrap)
	}

}

function cms_translate_string_show_save_first(){

	get_ajax_panel('cms/cms_popup_yes_no', {
		'text': 'Save the panel first to edit translations.',
	}, function(data){
		panels_display_popup(data.result._html, {})
	})

}

function cms_translate_string_collect_values($container){

	var values = {}

	$container.find('.cms_translate_string_input').each(function(){
		var language_id = $(this).attr('data-language_id') || $(this).data('language_id')
		if (!language_id){
			return
		}
		values[language_id] = $(this).val()
	})

	return values

}

function cms_translate_string_sync_origin(values, $container, save_result){

	var context = cms_translate_string_get_marked_context()

	if (!context || !context.$field.length){
		return
	}

	var value = undefined

	if (save_result && save_result.sync_value !== null && typeof save_result.sync_value !== 'undefined'){
		value = save_result.sync_value
	}

	if (typeof value === 'undefined'){
		var cms_language = cms_translate_string_get_cms_language()
		if (!cms_language){
			return
		}
		value = cms_translate_string_popup_value_for_language($container, cms_language)
		if (typeof value === 'undefined'){
			value = cms_translate_string_value_for_language(values, cms_language)
		}
	}

	if (typeof value === 'undefined'){
		return
	}

	cms_translate_string_set_field_value(context.$field, value, context.field_type, context.$input_wrap)

	if (typeof cms_page_panel_schedule_title_preview === 'function'){
		cms_page_panel_schedule_title_preview()
	}

}

function cms_translate_string_init_colour($container){

	$container.find('.cms_translate_string_colour').each(function(){

		var $row = $(this)
		var $helper = $('.cms_translate_string_colour_helper', $row)
		var $input = $('.cms_translate_string_colour_input', $row)

		$helper.off('change.cms_translate').on('change.cms_translate', function(){
			$input.val($(this).val())
		})

		$input.off('change.cms_translate keyup.cms_translate').on('change.cms_translate keyup.cms_translate', function(){
			var val = $(this).val().trim()
			if (/^#[0-9A-F]{6}$/i.test(val)){
				$helper.val(val)
			}
		})

		var initial = $input.val().trim()
		if (/^#[0-9A-F]{6}$/i.test(initial)){
			$helper.val(initial)
		}

	})

}

function cms_translate_string_notification(text, timer){

	var $container = $('.cms_translate_string_container')

	if (!$container.length){
		return
	}

	var $toolbar = $container.find('.cms_translate_string_toolbar')

	$toolbar.find('.cms_translate_string_notification').remove()

	var $note = $('<div class="cms_translate_string_notification"></div>').text(text)

	$toolbar.append($note)

	setTimeout(function(){
		$note.css({'opacity': '1'})
	}, 100)

	if (timer){
		setTimeout(function(){
			$note.css({'opacity': '0'})
			setTimeout(function(){
				$note.remove()
			}, 600)
		}, timer * 1000)
	}

}

function cms_translate_string_remove_popup(){

	$('.cms_translate_string_overlay, .cms_translate_string_container').remove()

}

function cms_translate_string_append_html(html){

	var $appended = $($.parseHTML(html, document, true))

	$('body').append($appended)

	return $appended.filter('.cms_translate_string_container')
		.add($appended.find('.cms_translate_string_container')).first()

}

function cms_translate_string_close(){

	cms_translate_string_remove_popup()
	cms_translate_string_clear_origin_mark()
	cms_translate_string_origin = null

}

function cms_translate_string_init($root){

	// $root may be the container itself (open) or a parent (find descendants)
	var $containers = (!$root || !$root.length)
		? $('.cms_translate_string_container')
		: $root.filter('.cms_translate_string_container').add($root.find('.cms_translate_string_container'))

	if (!$containers.length){
		return
	}

	$containers.not('.cms_translate_string_ok').each(function(){

		var $container = $(this)

		$container.addClass('cms_translate_string_ok')

		cms_translate_string_init_colour($container)

		$container.find('.cms_translate_string_save').off('click.cms_translate').on('click.cms_translate', function(){

			var values = cms_translate_string_collect_values($container)

			get_ajax_panel('cms/cms_translate_string_operations', {
				'do': 'cms_translate_string_save',
				'cms_page_panel_id': $container.data('cms_page_panel_id'),
				'field_name': $container.data('field_name'),
				'cms_language': cms_translate_string_get_cms_language(),
				'values': JSON.stringify(values),
			}, function(data){

				if (data.result && data.result.ok){
					cms_translate_string_sync_origin(values, $container, data.result)
					cms_translate_string_notification('Translations saved', 3)
				}

			})

		})

		$container.find('.cms_translate_string_close').off('click.cms_translate').on('click.cms_translate', function(){
			cms_translate_string_close()
		})

	})

}

function cms_translate_string_open(context){

	cms_translate_string_origin = context

	get_ajax_panel('cms/cms_translate_string', {
		'cms_page_panel_id': $('.cms_page_panel_id').val(),
		'field_name': context.field_name,
		'field_type': context.field_type,
	}, function(data){

		if (!data.result || !data.result._html){
			return
		}

		cms_translate_string_remove_popup()

		var $container = cms_translate_string_append_html(data.result._html)

		if (typeof cms_translate_string_init === 'function'){
			cms_translate_string_init($container)
		}

	})

}

function cms_translate_string_click(e){

	e.preventDefault()
	e.stopPropagation()

	var $icon = $(this)

	if (cms_translate_string_is_readonly_parent($icon)){
		return
	}

	var cms_page_panel_id = parseInt($('.cms_page_panel_id').val(), 10) || 0

	if (cms_page_panel_id < 1){
		cms_translate_string_show_save_first()
		return
	}

	var context = cms_translate_string_get_field_context($icon)

	if (!context.field_name){
		return
	}

	cms_translate_string_mark_origin($icon)
	cms_translate_string_open(context)

}

function cms_translate_string_bind(){

	$(document).off('click.cms_translate', '.cms_translate_icon').on('click.cms_translate', '.cms_translate_icon', cms_translate_string_click)

}

$(document).ready(function(){
	cms_translate_string_bind()
})