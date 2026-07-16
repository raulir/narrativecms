var cms_image_crop_drag = null
var cms_image_pan_x = 0
var cms_image_pan_y = 0
var cms_image_zoom = 1.0
var cms_image_zoom_min = 0.5
var cms_image_zoom_max = 16.0
var cms_image_zoom_default = 1.0
var cms_image_brightness = 0.5
var cms_image_contrast = 0.5
var cms_image_overlay_opacity = 0.0
var cms_image_overlay_opacity_default = 0.0
var cms_image_overlay_colour = '#000000'
var cms_image_rotation = 0
var cms_image_rotation_min = -180
var cms_image_rotation_max = 180
var cms_image_rotation_default = 0
var cms_image_rotation_fixed = true
var cms_image_rotation_step = 45
var cms_image_level_default = 0.5
var cms_image_level_min = 0
var cms_image_level_max = 1
var cms_image_layout = {}

function cms_image_crop_format(value){
	return (parseFloat(value) || 0).toFixed(1)
}

function cms_image_zoom_format(value){
	return cms_image_zoom_clamp(value).toFixed(1)
}

function cms_image_zoom_clamp(value){
	var v = parseFloat(value)
	if (isNaN(v)){
		v = cms_image_zoom_default
	}
	v = Math.max(cms_image_zoom_min, Math.min(cms_image_zoom_max, v))
	return Math.round(v * 10) / 10
}

function cms_image_zoom_log_bounds(){
	return {
		min: Math.log2(cms_image_zoom_min),
		max: Math.log2(cms_image_zoom_max),
	}
}

function cms_image_zoom_value_to_percent(value){
	var bounds = cms_image_zoom_log_bounds()
	return ((Math.log2(cms_image_zoom_clamp(value)) - bounds.min) / (bounds.max - bounds.min)) * 100
}

function cms_image_zoom_percent_to_value(percent){
	var bounds = cms_image_zoom_log_bounds()
	var log_val = bounds.min + (percent / 100) * (bounds.max - bounds.min)
	return cms_image_zoom_clamp(Math.pow(2, log_val))
}

function cms_image_zoom_update_handle(){
	$('.cms_image_zoom_slider_handle').css({
		left: cms_image_zoom_value_to_percent(cms_image_zoom) + '%',
	})
}

function cms_image_zoom_set(value, skip_input){
	cms_image_zoom = cms_image_zoom_clamp(value)
	if (skip_input !== true){
		$('.cms_image_zoom_input').val(cms_image_zoom_format(cms_image_zoom))
	}
	cms_image_zoom_update_handle()
	cms_image_resize()
}

function cms_image_zoom_pointer_to_value(page_x){

	var $inner = $('.cms_image_zoom_slider_inner')
	var offset = $inner.offset()
	var width = $inner.innerWidth()
	var x = page_x - offset.left
	var percent = width ? Math.max(0, Math.min(100, (x / width) * 100)) : 0

	return cms_image_zoom_percent_to_value(percent)

}

function cms_image_rotation_format(value){
	return String(cms_image_rotation_clamp(value))
}

function cms_image_rotation_clamp_raw(value){
	var v = parseFloat(value)
	if (isNaN(v)){
		v = cms_image_rotation_default
	}
	return Math.max(cms_image_rotation_min, Math.min(cms_image_rotation_max, v))
}

function cms_image_rotation_snap(value){
	var v = cms_image_rotation_clamp_raw(value)
	return Math.round(v / cms_image_rotation_step) * cms_image_rotation_step
}

function cms_image_rotation_clamp(value, skip_snap){
	var v = cms_image_rotation_clamp_raw(value)
	if (cms_image_rotation_fixed && skip_snap !== true){
		return cms_image_rotation_snap(v)
	}
	return Math.round(v)
}

function cms_image_rotation_value_to_percent(value){
	return ((cms_image_rotation_clamp(value) - cms_image_rotation_min) / (cms_image_rotation_max - cms_image_rotation_min)) * 100
}

function cms_image_rotation_percent_to_value(percent){
	var ratio = Math.max(0, Math.min(100, percent)) / 100
	return cms_image_rotation_clamp(cms_image_rotation_min + ratio * (cms_image_rotation_max - cms_image_rotation_min))
}

function cms_image_rotation_update_handle(){
	$('.cms_image_rotation_slider_handle').css({
		left: cms_image_rotation_value_to_percent(cms_image_rotation) + '%',
	})
}

function cms_image_rotation_set(value, skip_input, skip_snap){
	cms_image_rotation = cms_image_rotation_clamp(value, skip_snap)
	if (skip_input !== true){
		$('.cms_image_rotation_input').val(cms_image_rotation_format(cms_image_rotation))
	}
	cms_image_rotation_update_handle()
	cms_image_apply_view()
}

function cms_image_rotation_sync_fixed_ui(){
	var $btn = $('.cms_image_rotation_fixed')
	if (cms_image_rotation_fixed){
		$btn.addClass('cms_image_rotation_fixed_on').attr('aria-pressed', 'true')
	} else {
		$btn.removeClass('cms_image_rotation_fixed_on').attr('aria-pressed', 'false')
	}
}

function cms_image_rotation_clear_fixed(){
	if (!cms_image_rotation_fixed){
		return
	}
	cms_image_rotation_fixed = false
	cms_image_rotation_sync_fixed_ui()
}

function cms_image_rotation_pointer_to_value(page_x){

	var $inner = $('.cms_image_rotation_slider_inner')
	var offset = $inner.offset()
	var width = $inner.innerWidth()
	var x = page_x - offset.left
	var percent = width ? Math.max(0, Math.min(100, (x / width) * 100)) : 0

	return cms_image_rotation_percent_to_value(percent)

}

function cms_image_level_clamp(value){
	var v = parseFloat(value)
	if (isNaN(v)){
		v = cms_image_level_default
	}
	v = Math.max(cms_image_level_min, Math.min(cms_image_level_max, v))
	return Math.round(v * 100) / 100
}

function cms_image_level_format(value){
	return cms_image_level_clamp(value).toFixed(2)
}

function cms_image_brightness_to_filter_amount(value){
	if (typeof cms_media_view_brightness_to_filter_amount === 'function'){
		return cms_media_view_brightness_to_filter_amount(value)
	}
	return 1
}

function cms_image_contrast_to_filter_amount(value){
	if (typeof cms_media_view_contrast_to_filter_amount === 'function'){
		return cms_media_view_contrast_to_filter_amount(value)
	}
	return 1
}

function cms_image_level_get(field){
	if (field === 'brightness'){
		return cms_image_brightness
	}
	if (field === 'opacity'){
		return cms_image_overlay_opacity
	}
	return cms_image_contrast
}

function cms_image_level_set_state(field, value){
	if (field === 'brightness'){
		cms_image_brightness = value
	} else if (field === 'opacity'){
		cms_image_overlay_opacity = value
	} else {
		cms_image_contrast = value
	}
}

function cms_image_level_value_to_percent(value){
	return cms_image_level_clamp(value) * 100
}

function cms_image_level_percent_to_value(percent){
	return cms_image_level_clamp(percent / 100)
}

function cms_image_level_update_handle(field){
	$('.cms_image_' + field + '_slider_handle').css({
		left: cms_image_level_value_to_percent(cms_image_level_get(field)) + '%',
	})
}

function cms_image_level_set(field, value, skip_input){
	cms_image_level_set_state(field, cms_image_level_clamp(value))
	if (skip_input !== true){
		$('.cms_image_' + field + '_input').val(cms_image_level_format(cms_image_level_get(field)))
	}
	cms_image_level_update_handle(field)
	if (field === 'opacity'){
		cms_image_apply_overlay()
	} else {
		cms_image_apply_view()
	}
}

function cms_image_level_pointer_to_value(page_x, field){

	var $inner = $('.cms_image_' + field + '_slider_inner')
	var offset = $inner.offset()
	var width = $inner.innerWidth()
	var x = page_x - offset.left
	var percent = width ? Math.max(0, Math.min(100, (x / width) * 100)) : 0

	return cms_image_level_percent_to_value(percent)

}

function cms_image_crop_center(){

	var $rect = $('.cms_image_crop_rect')

	if ($rect.length && cms_image_layout.img_w){
		var left = parseFloat($rect.css('left')) || 0
		var top = parseFloat($rect.css('top')) || 0

		return {
			x: left + ($rect.outerWidth() / 2),
			y: top + ($rect.outerHeight() / 2),
		}
	}

	var box = cms_image_crop_box_from_values()

	return {
		x: (box.left + box.right) / 2,
		y: (box.top + box.bottom) / 2,
	}

}

function cms_image_apply_view(){

	var b_n = cms_image_brightness_to_filter_amount(cms_image_brightness)
	var c_n = cms_image_contrast_to_filter_amount(cms_image_contrast)
	var center = cms_image_crop_center()
	var deg = cms_image_rotation

	$('.cms_image_image_source').css({
		filter: 'brightness(' + b_n + ') contrast(' + c_n + ')',
	})

	$('.cms_image_image_rotate').css({
		transform: deg ? 'rotate(' + deg + 'deg)' : '',
		'transform-origin': center.x + 'px ' + center.y + 'px',
	})

	if (cms_image_is_source_video()){
		var $video_el = $('.cms_image_image_source video.cms_video_player')
		if ($video_el.length && $video_el[0].paused){
			cms_image_video_safe_play($video_el)
		}
	}

}

function cms_image_apply_filters(){
	cms_image_apply_view()
}

function cms_image_hex_to_rgb(hex){

	if (typeof cms_media_view_hex_to_rgb === 'function'){
		return cms_media_view_hex_to_rgb(hex)
	}

	return null

}

function cms_image_get_overlay_colour(){

	var value = $('.cms_image_element_overlay .cms_input_colour_input').val()
	if (value){
		return value.trim()
	}

	return cms_image_overlay_colour

}

function cms_image_apply_overlay(){

	var colour = cms_image_get_overlay_colour()
	var rgb = cms_image_hex_to_rgb(colour)
	var opacity = cms_image_overlay_opacity
	var $preview = $('.cms_image_overlay_preview')

	cms_image_overlay_colour = colour

	if (!rgb || opacity <= 0.005){
		$preview.css({
			display: 'none',
			'background-color': '',
		})
		return
	}

	$preview.css({
		display: 'block',
		'background-color': 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + opacity + ')',
	})

}

function cms_image_overlay_bind_colour(){

	if (typeof cms_input_colour_init === 'function'){
		cms_input_colour_init()
	}

	$('.cms_image_element_overlay .cms_input_colour_input').off('change.cms_image input.cms_image keyup.cms_image')
		.on('change.cms_image input.cms_image keyup.cms_image', function(){
			cms_image_apply_overlay()
		})

	$('.cms_image_element_overlay .cms_input_colour_helper').off('change.cms_image')
		.on('change.cms_image', function(){
			cms_image_apply_overlay()
		})

	$('.cms_image_element_overlay .cms_input_colour_default').off('click.cms_image')
		.on('click.cms_image', function(){
			setTimeout(cms_image_apply_overlay, 0)
		})

}

function cms_image_level_bind_input(field){

	$('.cms_image_' + field + '_input').off('input.cms change.cms blur.cms')
		.on('input.cms', function(){
			var raw = $(this).val()
			if (raw === '' || raw === '-'){
				return
			}
			var v = parseFloat(raw)
			if (!isNaN(v)){
				cms_image_level_set(field, v, true)
			}
		})
		.on('change.cms blur.cms', function(){
			cms_image_level_set(field, $(this).val())
		})

}

function cms_image_level_bind_slider(field){

	$('.cms_image_' + field + '_slider_handle').off('mousedown.cms').on('mousedown.cms', function(e){
		e.preventDefault()
		e.stopPropagation()

		cms_image_crop_drag = {
			type: 'level',
			field: field,
		}

		$('.cms_image_' + field + '_slider_handle').addClass('cms_image_level_dragging')
	})

	$('.cms_image_' + field + '_slider_inner').off('mousedown.cms').on('mousedown.cms', function(e){
		if ($(e.target).hasClass('cms_image_' + field + '_slider_handle')){
			return
		}

		e.preventDefault()
		cms_image_level_set(field, cms_image_level_pointer_to_value(e.pageX, field))
	})

}

function cms_image_crop_get_values(){
	return {
		x1: parseFloat($('.cms_image_crop_x1').val()) || 0,
		y1: parseFloat($('.cms_image_crop_y1').val()) || 0,
		x2: parseFloat($('.cms_image_crop_x2').val()) || 0,
		y2: parseFloat($('.cms_image_crop_y2').val()) || 0,
	}
}

function cms_image_crop_is_full(){

	var crop = cms_image_crop_get_values()

	return Math.abs(crop.x1) < 0.05
		&& Math.abs(crop.y1) < 0.05
		&& Math.abs(crop.x2 - 100) < 0.05
		&& Math.abs(crop.y2 - 100) < 0.05

}

function cms_image_save_overlay_show(){

	var $overlay = $('.cms_image_save_overlay')
	$overlay.addClass('cms_image_save_overlay_visible')
	setTimeout(function(){
		if ($overlay.hasClass('cms_image_save_overlay_visible')){
			$overlay.addClass('cms_image_save_overlay_show_label')
		}
	}, 1000)

}

function cms_image_is_source_video(){

	return $('.cms_image_container').data('is_source_video') == 1

}

function cms_image_video_safe_play($video_el){

	if (typeof cms_video_safe_play === 'function'){
		cms_video_safe_play($video_el)
	}

}

function cms_image_video_cleanup(){

	if (typeof cms_video_cleanup === 'function'){
		cms_video_cleanup($('.cms_image_container'))
	}

}

function cms_image_video_init($root){

	var $containers = $root ? ($root.hasClass('cms_image_container') ? $root : $root.find('.cms_image_container')) : $('.cms_image_container');

	$containers.not('.cms_image_video_ok').each(function(){

	var $container = $(this)

	if ($container.data('is_source_video') != 1){
		return
	}

	$container.addClass('cms_image_video_ok')

	var $source = $('.cms_image_image_source[data-cms_video]', $container)
	if (!$source.length){
		return
	}

	if (typeof cms_video_wrapper !== 'function' || typeof cms_video_fallback !== 'function'){
		return
	}

	var $video_el = $source.find('video.cms_video_player')

	if ($video_el.length){
		$video_el.css({
			position: 'absolute',
			left: 0,
			top: 0,
			width: '100%',
			height: '100%',
			'object-fit': 'fill',
			transform: '',
			'max-width': '',
			'max-height': '',
		})
		cms_image_video_safe_play($video_el)
		$source.data('cms_image_video_ready', 1)
		return
	}

	if ($source.data('cms_image_video_ready')){
		return
	}

	var $video_wrapper = cms_video_wrapper($source, $source, { force_fill: 1 })
	$source.css({'background-image': ''})
	$source.empty().append($video_wrapper)
	$video_el = $source.find('video.cms_video_player')

	$video_el.off('loadedmetadata.cms_image').on('loadedmetadata.cms_image', function(){
		if (this.videoWidth && this.videoHeight){
			$source.data('w', this.videoWidth)
			$source.data('h', this.videoHeight)
			cms_image_resize()
		}
	})

	cms_video_fallback($video_el, true)
	$source.data('cms_image_video_ready', 1)

	})

}

function cms_image_crop_set_values(crop, format){
	if (format !== false){
		$('.cms_image_crop_x1').val(cms_image_crop_format(crop.x1))
		$('.cms_image_crop_y1').val(cms_image_crop_format(crop.y1))
		$('.cms_image_crop_x2').val(cms_image_crop_format(crop.x2))
		$('.cms_image_crop_y2').val(cms_image_crop_format(crop.y2))
	} else {
		$('.cms_image_crop_x1').val(crop.x1)
		$('.cms_image_crop_y1').val(crop.y1)
		$('.cms_image_crop_x2').val(crop.x2)
		$('.cms_image_crop_y2').val(crop.y2)
	}
}

function cms_image_crop_box_from_values(){

	var crop = cms_image_crop_get_values()

	return {
		left: (crop.x1 / 100) * cms_image_layout.img_w,
		top: (crop.y1 / 100) * cms_image_layout.img_h,
		right: (crop.x2 / 100) * cms_image_layout.img_w,
		bottom: (crop.y2 / 100) * cms_image_layout.img_h,
	}

}

function cms_image_crop_values_from_box(box){

	return {
		x1: (box.left / cms_image_layout.img_w) * 100,
		y1: (box.top / cms_image_layout.img_h) * 100,
		x2: (box.right / cms_image_layout.img_w) * 100,
		y2: (box.bottom / cms_image_layout.img_h) * 100,
	}

}

function cms_image_crop_apply_box(box){

	var left = Math.min(box.left, box.right)
	var top = Math.min(box.top, box.bottom)
	var right = Math.max(box.left, box.right)
	var bottom = Math.max(box.top, box.bottom)

	$('.cms_image_crop_handle_tl').css({ left: box.left + 'px', top: box.top + 'px' })
	$('.cms_image_crop_handle_tr').css({ left: box.right + 'px', top: box.top + 'px' })
	$('.cms_image_crop_handle_bl').css({ left: box.left + 'px', top: box.bottom + 'px' })
	$('.cms_image_crop_handle_br').css({ left: box.right + 'px', top: box.bottom + 'px' })
	$('.cms_image_crop_rect').css({
		left: left + 'px',
		top: top + 'px',
		width: (right - left) + 'px',
		height: (bottom - top) + 'px',
	})

}

function cms_image_crop_update_overlay(){

	if (!cms_image_layout.img_w){
		return
	}

	cms_image_crop_apply_box(cms_image_crop_box_from_values())
	cms_image_apply_view()

}

function cms_image_crop_sync_values_from_box(box){

	if (!cms_image_layout.img_w){
		return
	}

	cms_image_crop_set_values(cms_image_crop_values_from_box(box))
	cms_image_crop_apply_box(box)

}

function cms_image_crop_pointer_to_pan(page_x, page_y){

	var offset = $('.cms_image_image_pan').offset()

	return {
		x: page_x - offset.left,
		y: page_y - offset.top,
	}

}

function cms_image_apply_pan(){

	$('.cms_image_image_pan').css({
		transform: 'translate(' + cms_image_pan_x + 'px, ' + cms_image_pan_y + 'px)',
	})

}

function cms_image_crop_bind_inputs(){

	$('.cms_image_crop_input').off('input.cms change.cms').on('input.cms change.cms', function(){
		cms_image_crop_update_overlay()
	})

}

function cms_image_zoom_bind_input(){

	$('.cms_image_zoom_input').off('input.cms change.cms blur.cms')
		.on('input.cms', function(){
			var raw = $(this).val()
			if (raw === '' || raw === '-'){
				return
			}
			var v = parseFloat(raw)
			if (!isNaN(v)){
				cms_image_zoom_set(v, true)
			}
		})
		.on('change.cms blur.cms', function(){
			cms_image_zoom_set($(this).val())
		})

}

function cms_image_zoom_bind_wheel(){

	var el = $('.cms_image_area')[0]
	if (!el){
		return
	}

	if (el.cms_image_wheel_handler){
		el.removeEventListener('wheel', el.cms_image_wheel_handler)
	}

	el.cms_image_wheel_handler = function(e){
		e.preventDefault()

		var next = cms_image_zoom
		if (e.deltaY < 0){
			next = cms_image_zoom * 1.1
		} else if (e.deltaY > 0){
			next = cms_image_zoom / 1.1
		}

		cms_image_zoom_set(next)
	}

	el.addEventListener('wheel', el.cms_image_wheel_handler, {passive: false})

}

function cms_image_rotation_bind_input(){

	$('.cms_image_rotation_input').off('input.cms change.cms blur.cms')
		.on('input.cms', function(){
			cms_image_rotation_clear_fixed()
			var raw = $(this).val()
			if (raw === '' || raw === '-'){
				return
			}
			var v = parseFloat(raw)
			if (!isNaN(v)){
				cms_image_rotation_set(v, true, true)
			}
		})
		.on('change.cms blur.cms', function(){
			cms_image_rotation_clear_fixed()
			cms_image_rotation_set($(this).val(), false, true)
		})

}

function cms_image_rotation_bind_fixed(){

	$('.cms_image_rotation_fixed').off('click.cms').on('click.cms', function(e){
		e.preventDefault()
		cms_image_rotation_fixed = !cms_image_rotation_fixed
		cms_image_rotation_sync_fixed_ui()
		if (cms_image_rotation_fixed){
			cms_image_rotation_set(cms_image_rotation)
		}
	})

}

function cms_image_rotation_bind_slider(){

	$('.cms_image_rotation_slider_handle').off('mousedown.cms').on('mousedown.cms', function(e){
		e.preventDefault()
		e.stopPropagation()

		cms_image_crop_drag = {
			type: 'rotation',
		}

		$('.cms_image_rotation_slider_handle').addClass('cms_image_rotation_dragging')
	})

	$('.cms_image_rotation_slider_inner, .cms_image_rotation_slider_track').off('mousedown.cms').on('mousedown.cms', function(e){
		if ($(e.target).hasClass('cms_image_rotation_slider_handle')){
			return
		}

		e.preventDefault()
		cms_image_rotation_set(cms_image_rotation_pointer_to_value(e.pageX))
	})

}

function cms_image_zoom_bind_slider(){

	$('.cms_image_zoom_slider_handle').off('mousedown.cms').on('mousedown.cms', function(e){
		e.preventDefault()
		e.stopPropagation()

		cms_image_crop_drag = {
			type: 'zoom',
		}

		$('.cms_image_zoom_slider_handle').addClass('cms_image_zoom_dragging')
	})

	$('.cms_image_zoom_slider_inner').off('mousedown.cms').on('mousedown.cms', function(e){
		if ($(e.target).hasClass('cms_image_zoom_slider_handle')){
			return
		}

		e.preventDefault()
		cms_image_zoom_set(cms_image_zoom_pointer_to_value(e.pageX))
	})

}

function cms_image_crop_bind_corner_drag(){

	$('.cms_image_crop_handle').off('mousedown.cms').on('mousedown.cms', function(e){
		e.preventDefault()
		e.stopPropagation()

		var $handle = $(this)
		var corner = 'tl'
		if ($handle.hasClass('cms_image_crop_handle_tr')) corner = 'tr'
		if ($handle.hasClass('cms_image_crop_handle_bl')) corner = 'bl'
		if ($handle.hasClass('cms_image_crop_handle_br')) corner = 'br'

		cms_image_crop_drag = {
			type: 'corner',
			corner: corner,
			box: cms_image_crop_box_from_values(),
		}
	})

}

function cms_image_crop_bind_pan_drag(){

	$('.cms_image_image_pan').off('mousedown.cms').on('mousedown.cms', function(e){
		e.preventDefault()

		cms_image_crop_drag = {
			type: 'pan',
			start_x: e.pageX,
			start_y: e.pageY,
			orig_pan_x: cms_image_pan_x,
			orig_pan_y: cms_image_pan_y,
		}

		$('.cms_image_image_pan').addClass('cms_image_crop_dragging')
	})

}

function cms_image_crop_bind_document_drag(){

	$(document).off('mousemove.cms_image_crop mouseup.cms_image_crop')

	$(document).on('mousemove.cms_image_crop', function(e){
		if (!cms_image_crop_drag){
			return
		}

		if (cms_image_crop_drag.type === 'corner'){
			var point = cms_image_crop_pointer_to_pan(e.pageX, e.pageY)
			var box = cms_image_crop_drag.box

			if (cms_image_crop_drag.corner === 'tl'){
				box.left = point.x
				box.top = point.y
			} else if (cms_image_crop_drag.corner === 'tr'){
				box.right = point.x
				box.top = point.y
			} else if (cms_image_crop_drag.corner === 'bl'){
				box.left = point.x
				box.bottom = point.y
			} else if (cms_image_crop_drag.corner === 'br'){
				box.right = point.x
				box.bottom = point.y
			}

			cms_image_crop_apply_box(box)
		}

		if (cms_image_crop_drag.type === 'pan'){
			cms_image_pan_x = cms_image_crop_drag.orig_pan_x + (e.pageX - cms_image_crop_drag.start_x)
			cms_image_pan_y = cms_image_crop_drag.orig_pan_y + (e.pageY - cms_image_crop_drag.start_y)
			cms_image_apply_pan()
		}

		if (cms_image_crop_drag.type === 'zoom'){
			cms_image_zoom_set(cms_image_zoom_pointer_to_value(e.pageX))
		}

		if (cms_image_crop_drag.type === 'level'){
			cms_image_level_set(cms_image_crop_drag.field, cms_image_level_pointer_to_value(e.pageX, cms_image_crop_drag.field))
		}

		if (cms_image_crop_drag.type === 'rotation'){
			cms_image_rotation_set(cms_image_rotation_pointer_to_value(e.pageX))
		}
	})

	$(document).on('mouseup.cms_image_crop', function(){
		if (!cms_image_crop_drag){
			return
		}

		if (cms_image_crop_drag.type === 'corner'){
			cms_image_crop_sync_values_from_box(cms_image_crop_drag.box)
		}

		if (cms_image_crop_drag.type === 'pan'){
			$('.cms_image_image_pan').removeClass('cms_image_crop_dragging')
		}

		if (cms_image_crop_drag.type === 'zoom'){
			$('.cms_image_zoom_input').val(cms_image_zoom_format(cms_image_zoom))
			$('.cms_image_zoom_slider_handle').removeClass('cms_image_zoom_dragging')
		}

		if (cms_image_crop_drag.type === 'level'){
			var field = cms_image_crop_drag.field
			$('.cms_image_' + field + '_input').val(cms_image_level_format(cms_image_level_get(field)))
			$('.cms_image_' + field + '_slider_handle').removeClass('cms_image_level_dragging')
		}

		if (cms_image_crop_drag.type === 'rotation'){
			$('.cms_image_rotation_input').val(cms_image_rotation_format(cms_image_rotation))
			$('.cms_image_rotation_slider_handle').removeClass('cms_image_rotation_dragging')
		}

		cms_image_crop_drag = null
	})

}

function cms_image_crop_init($root){

	var $containers = $root ? ($root.hasClass('cms_image_container') ? $root : $root.find('.cms_image_container')) : $('.cms_image_container');

	$containers.not('.cms_image_crop_ok').each(function(){

	var $container = $(this)

	$container.addClass('cms_image_crop_ok')

	cms_image_pan_x = parseFloat($container.data('pan_x')) || 0
	cms_image_pan_y = parseFloat($container.data('pan_y')) || 0
	cms_image_zoom = cms_image_zoom_clamp(parseFloat($container.data('zoom')) || cms_image_zoom_default)
	cms_image_brightness = cms_image_level_clamp(parseFloat($container.data('brightness')) || cms_image_level_default)
	cms_image_contrast = cms_image_level_clamp(parseFloat($container.data('contrast')) || cms_image_level_default)
	cms_image_overlay_opacity = cms_image_level_clamp(parseFloat($container.data('overlay_opacity')) || cms_image_overlay_opacity_default)
	cms_image_overlay_colour = $container.data('overlay_colour') || '#000000'
	cms_image_rotation_fixed = String($container.data('rotation_fixed')) !== '0'
	cms_image_rotation = cms_image_rotation_clamp(parseFloat($container.data('rotation')) || cms_image_rotation_default)
	cms_image_rotation_sync_fixed_ui()

	$('.cms_image_zoom_input').val(cms_image_zoom_format(cms_image_zoom))
	cms_image_zoom_update_handle()
	$('.cms_image_brightness_input').val(cms_image_level_format(cms_image_brightness))
	$('.cms_image_contrast_input').val(cms_image_level_format(cms_image_contrast))
	$('.cms_image_opacity_input').val(cms_image_level_format(cms_image_overlay_opacity))
	cms_image_level_update_handle('brightness')
	cms_image_level_update_handle('contrast')
	cms_image_level_update_handle('opacity')
	$('.cms_image_rotation_input').val(cms_image_rotation_format(cms_image_rotation))
	cms_image_rotation_update_handle()

	cms_image_crop_bind_inputs()
	cms_image_zoom_bind_input()
	cms_image_zoom_bind_wheel()
	cms_image_zoom_bind_slider()
	cms_image_rotation_bind_input()
	cms_image_rotation_bind_slider()
	cms_image_rotation_bind_fixed()
	cms_image_level_bind_input('brightness')
	cms_image_level_bind_input('contrast')
	cms_image_level_bind_input('opacity')
	cms_image_level_bind_slider('brightness')
	cms_image_level_bind_slider('contrast')
	cms_image_level_bind_slider('opacity')
	cms_image_overlay_bind_colour()
	cms_image_crop_bind_corner_drag()
	cms_image_crop_bind_pan_drag()
	cms_image_crop_bind_document_drag()
	cms_image_crop_update_overlay()
	cms_image_apply_view()
	cms_image_apply_overlay()

	})

}

function cms_image_init($root){

	var $containers = $root ? ($root.hasClass('cms_image_container') ? $root : $root.find('.cms_image_container')) : $('.cms_image_container');

	$containers.not('.cms_image_ok').each(function(){

	var $container = $(this)

	$container.addClass('cms_image_ok')

	$('.cms_image_cancel').off('click.cms').on('click.cms', function(){
		cms_image_destroy($container)
		$('.cms_image_overlay,.cms_image_container').remove()
	})

	$('.cms_image_save', $container).off('click.cms').on('click.cms', function(){

		if ($(this).hasClass('cms_image_save_disabled')){
			return
		}

		var $container = $('.cms_image_container')
		var is_child = $container.data('is_child') == 1
		var needs_export_overlay = is_child || !cms_image_crop_is_full()

		if (needs_export_overlay){
			cms_image_save_overlay_show()
			$('.cms_image_save').addClass('cms_image_save_disabled')
		}

		get_ajax('cms/cms_images', {
			'filename': $(this).data('filename'),
			'do': 'cms_images_save',
			'source_cms_image_id': $('.cms_image_container').data('source_cms_image_id'),
			'author': $('.cms_image_author').val(),
			'copyright': $('.cms_image_copyright').val(),
			'description': $('.cms_image_description').val(),
			'category': $('.cms_image_category').val(),
			'crop_x1': $('.cms_image_crop_x1').val(),
			'crop_y1': $('.cms_image_crop_y1').val(),
			'crop_x2': $('.cms_image_crop_x2').val(),
			'crop_y2': $('.cms_image_crop_y2').val(),
			'zoom': $('.cms_image_zoom_input').val(),
			'pan_x': cms_image_pan_x,
			'pan_y': cms_image_pan_y,
			'brightness': $('.cms_image_brightness_input').val(),
			'contrast': $('.cms_image_contrast_input').val(),
			'overlay_colour': cms_image_get_overlay_colour(),
			'overlay_opacity': $('.cms_image_opacity_input').val(),
			'rotation': $('.cms_image_rotation_input').val(),
			'rotation_fixed': cms_image_rotation_fixed ? '1' : '0',
			'success': function(data){

				var $container = $('.cms_image_container')
				var is_child = $container.data('is_child') == 1

				if ($('.cms_images_category').val() != $('.cms_image_category').val()){
					$('.cms_images_category').val($('.cms_image_category').val())
					var page = 0
				} else {
					var page = $('.cms_images_area').data('page')
					if ($('.cms_images_image').length == 1 && page > 0){
						page = page - 1
					}
				}

				var reopen_filename = data.result && data.result.child_filename ? data.result.child_filename : ''
				var parent_filename = $('.cms_image_save').data('filename')
				var reload_filename = cms_images_get_selected_filename()

				if (is_child){
					$('.cms_images_area').data('edited_filename', parent_filename)
				} else if (reopen_filename){
					$('.cms_images_area').data('edited_filename', reopen_filename)
					$('.cms_images_area').data('edited_from_filename', parent_filename)
					reload_filename = reopen_filename
					if (typeof cms_images_set_popup_selection === 'function'){
						cms_images_set_popup_selection(reopen_filename)
					}
				}

				cms_image_video_cleanup()
				$('.cms_image_overlay,.cms_image_container').remove()

				cms_images_load_images(
					page,
					$('.cms_images_area').data('limit'),
					reload_filename
				)

				if (!is_child && reopen_filename && typeof cms_image_open === 'function'){
					cms_image_open(reopen_filename)
				}

			}
		})

	})

	cms_image_crop_init($container)
	cms_image_video_init($container)

	})

	if ($containers.filter('.cms_image_ok').length){
		$(window).off('resize.cms_image').on('resize.cms_image', cms_image_resize)
		$(window).off('scroll.cms_image').on('scroll.cms_image', cms_image_scroll)
	}

}

function cms_image_destroy($root){

	var $scope = $root ? ($root.hasClass('cms_image_container') ? $root : $root.find('.cms_image_container')) : $('.cms_image_container');

	cms_image_video_cleanup()

	$scope.filter('.cms_image_ok').each(function(){

		var $container = $(this)

		$container.removeClass('cms_image_ok cms_image_crop_ok cms_image_video_ok')
		$container.off('.cms')

	})

	$('.cms_image_cancel, .cms_image_save').off('.cms')

	$(document).off('mousemove.cms_image_crop mouseup.cms_image_crop')
	$(window).off('resize.cms_image scroll.cms_image')

	cms_image_crop_drag = null

}

function cms_image_resize(){

	var $area = $('.cms_image_area')
	var $pan = $('.cms_image_image_pan')
	var $source = $('.cms_image_image_source')

	if (!$source.length || !$source.data('w')){
		return
	}

	var natural_w = $source.data('w')
	var natural_h = $source.data('h')
	var area_width = $area.innerWidth()
	var area_height = $area.innerHeight()
	var diagonal = Math.sqrt(natural_w * natural_w + natural_h * natural_h)
	var base_scale = area_width / diagonal
	var img_w = natural_w * base_scale * cms_image_zoom
	var img_h = natural_h * base_scale * cms_image_zoom

	cms_image_layout = {
		area_w: area_width,
		area_h: area_height,
		img_w: img_w,
		img_h: img_h,
		base_scale: base_scale,
		base_left: (area_width - img_w) / 2,
		base_top: (area_height - img_h) / 2,
	}

	$pan.css({
		left: cms_image_layout.base_left + 'px',
		top: cms_image_layout.base_top + 'px',
		width: img_w + 'px',
		height: img_h + 'px',
	}).removeClass('cms_image_image_hidden')

	cms_image_apply_pan()
	cms_image_crop_update_overlay()
	cms_image_zoom_update_handle()
	cms_image_level_update_handle('brightness')
	cms_image_level_update_handle('contrast')
	cms_image_level_update_handle('opacity')
	cms_image_rotation_update_handle()
	cms_image_apply_view()
	cms_image_apply_overlay()

}

function cms_image_scroll(){

}

$(document).ready(function() {

	cms_image_init()

	if ($('.cms_image_container').length){
		cms_image_resize()
		cms_image_scroll()
	}

})