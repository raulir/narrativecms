var cms_preview_storage_key = 'cms_preview_mode'

function cms_preview_get_container(){
	return $('.cms_preview_container').first()
}

function cms_preview_get_mode(){
	var mode = ''
	try {
		mode = localStorage.getItem(cms_preview_storage_key) || ''
	} catch (e) {}
	if (mode !== 'desktop' && mode !== 'mobile'){
		mode = ''
	}
	return mode
}

function cms_preview_set_mode(mode){
	try {
		if (mode){
			localStorage.setItem(cms_preview_storage_key, mode)
		} else {
			localStorage.removeItem(cms_preview_storage_key)
		}
	} catch (e) {}
}

function cms_preview_set_highlight_cookie(highlight_id){
	if (highlight_id > 0){
		document.cookie = 'cms_preview_highlight=' + highlight_id + '; path=/; SameSite=Lax'
	} else {
		document.cookie = 'cms_preview_highlight=; path=/; max-age=0; SameSite=Lax'
	}
}

function cms_preview_is_open(){
	return cms_preview_get_mode() !== ''
}

function cms_preview_get_width_pct($container, mode){
	if (mode === 'mobile'){
		return parseInt($container.data('mobile_preview_width'), 10) || 40
	}
	return parseInt($container.data('desktop_preview_width'), 10) || 40
}

function cms_preview_get_header_px(){
	return $('.cms_header_container').outerHeight() || 60
}

function cms_preview_get_layout_width(){
	return document.documentElement.clientWidth || window.innerWidth || $(window).width()
}

function cms_preview_resize_after_layout(){
	requestAnimationFrame(function(){
		requestAnimationFrame(function(){
			cms_preview_resize()
		})
	})
}

function cms_preview_get_design_width($container){
	return parseInt($container.data('admin_design_width'), 10) || 1020
}

function cms_preview_get_preview_design_width($container, mode){
	if (mode === 'mobile'){
		return parseInt($container.data('preview_mobile_width'), 10) || 750
	}
	return parseInt($container.data('preview_desktop_width'), 10) || 1200
}

function cms_preview_cap_scale(raw_scale){
	return Math.min(1, raw_scale)
}

function cms_preview_clear_split_styles(){

	$('.cms_admin_content').css({
		'position': '',
		'left': '',
		'top': '',
		'width': '',
		'height': '',
		'max-height': '',
		'transform': '',
		'transform-origin': '',
		'margin': ''
	})

	$('.cms_admin_preview').css({
		'position': '',
		'left': '',
		'right': '',
		'top': '',
		'width': '',
		'height': ''
	})

	$('.cms_preview_frame_wrap').css({
		'width': '',
		'height': '',
		'margin-left': '',
		'transform': '',
		'transform-origin': ''
	})

	$('.cms_header_area').css({
		'margin-left': '',
		'margin-right': '',
		'width': ''
	})

}

function cms_preview_update_toggle_ui(mode){
	$('.cms_preview_toggle').removeClass('cms_preview_toggle_active')
	if (mode === 'desktop'){
		$('.cms_preview_toggle_desktop').addClass('cms_preview_toggle_active')
	} else if (mode === 'mobile'){
		$('.cms_preview_toggle_mobile').addClass('cms_preview_toggle_active')
	}
}

function cms_preview_resize(){

	var $container = cms_preview_get_container()
	var mode = cms_preview_get_mode()

	$('body').toggleClass('cms_preview_open', cms_preview_is_open())
	$('body').toggleClass('cms_preview_mode_desktop', mode === 'desktop')
	$('body').toggleClass('cms_preview_mode_mobile', mode === 'mobile')

	if (!cms_preview_is_open()){
		cms_preview_clear_split_styles()
		return
	}

	var window_width = cms_preview_get_layout_width()
	var header_px = cms_preview_get_header_px()
	var preview_pct = cms_preview_get_width_pct($container, mode)
	var preview_px = Math.floor(window_width * preview_pct / 100)
	var admin_px = window_width - preview_px
	var design_width = cms_preview_get_design_width($container)
	var admin_scale = cms_preview_cap_scale(admin_px / design_width)
	var admin_viewport_height = window.innerHeight - header_px
	var admin_left = 0
	var admin_panel_height = admin_viewport_height
	var preview_top = header_px
	var preview_viewport_height = admin_viewport_height

	if (admin_scale < 1){
		admin_panel_height = admin_viewport_height / admin_scale
	} else {
		preview_top = 0
		preview_viewport_height = window.innerHeight
		if (admin_px > design_width){
			admin_left = Math.round((admin_px - design_width) / 2)
		}
	}

	var $preview_pane = $('.cms_admin_preview')

	$preview_pane.css({
		'position': 'fixed',
		'left': admin_px + 'px',
		'right': '',
		'top': preview_top + 'px',
		'width': preview_px + 'px',
		'height': preview_viewport_height + 'px'
	})

	var preview_border_px = parseFloat($preview_pane.css('border-left-width')) || 0
	var preview_inner_px = preview_px - preview_border_px

	$('.cms_admin_content').css({
		'position': 'fixed',
		'left': admin_left + 'px',
		'top': header_px + 'px',
		'width': design_width + 'px',
		'height': admin_panel_height + 'px',
		'max-height': '',
		'transform': admin_scale < 1 ? 'scale(' + admin_scale + ')' : 'none',
		'transform-origin': 'top left',
		'margin': 0
	})

	$('.cms_header_area').css({
		'margin-left': admin_left + 'px',
		'margin-right': 'auto',
		'width': design_width + 'px'
	})

	var preview_design_width = cms_preview_get_preview_design_width($container, mode)
	var preview_scale = cms_preview_cap_scale(preview_inner_px / preview_design_width)
	var preview_frame_height = preview_scale < 1 ? preview_viewport_height / preview_scale : preview_viewport_height
	var preview_wrap_left = 0

	if (preview_scale >= 1 && preview_inner_px > preview_design_width){
		preview_wrap_left = Math.round((preview_inner_px - preview_design_width) / 2)
	}

	$('.cms_preview_frame_wrap', $container).css({
		'width': preview_design_width + 'px',
		'height': preview_frame_height + 'px',
		'margin-left': preview_wrap_left + 'px',
		'transform': preview_scale < 1 ? 'scale(' + preview_scale + ')' : 'none',
		'transform-origin': 'top left'
	})

}

function cms_preview_load_iframe(){

	var $container = cms_preview_get_container()
	if (!$container.length || !cms_preview_is_open()){
		return
	}

	var available = parseInt($container.data('preview_available'), 10) === 1
	var preview_url = $container.data('preview_url') || ''
	var $iframe = $('.cms_preview_iframe', $container)
	var $unavailable = $('.cms_preview_unavailable', $container)
	var $wrap = $('.cms_preview_frame_wrap', $container)

	if (!available || preview_url === ''){
		$unavailable.addClass('cms_preview_unavailable_visible')
		$wrap.hide()
		$iframe.attr('src', 'about:blank')
		cms_preview_set_highlight_cookie(0)
		return
	}

	$unavailable.removeClass('cms_preview_unavailable_visible')
	$wrap.show()

	var highlight_id = parseInt($container.data('preview_highlight_id'), 10) || 0
	cms_preview_set_highlight_cookie(highlight_id)

	if ($iframe.attr('src') !== preview_url){
		$iframe.attr('src', preview_url)
		$iframe.off('load.cms_preview_resize').on('load.cms_preview_resize', function(){
			cms_preview_resize_after_layout()
		})
	}

}

function cms_preview_open(mode){

	var current = cms_preview_get_mode()
	if (current === mode){
		cms_preview_close()
		return
	}

	cms_preview_set_mode(mode)
	cms_preview_update_toggle_ui(mode)
	cms_preview_resize()
	cms_preview_load_iframe()
	cms_preview_resize_after_layout()

}

function cms_preview_close(){

	cms_preview_set_mode('')
	cms_preview_update_toggle_ui('')
	cms_preview_set_highlight_cookie(0)
	cms_preview_resize()

	var $container = cms_preview_get_container()
	if ($container.length){
		$('.cms_preview_iframe', $container).attr('src', 'about:blank')
		$('.cms_preview_frame_wrap', $container).hide()
		$('.cms_preview_unavailable', $container).removeClass('cms_preview_unavailable_visible')
	}

}

function cms_preview_reload(){

	if (!cms_preview_is_open()){
		return
	}

	var $container = cms_preview_get_container()
	if (!$container.length){
		return
	}

	var available = parseInt($container.data('preview_available'), 10) === 1
	var preview_url = $container.data('preview_url') || ''

	if (!available || preview_url === ''){
		cms_preview_load_iframe()
		return
	}

	var highlight_id = parseInt($container.data('preview_highlight_id'), 10) || 0
	cms_preview_set_highlight_cookie(highlight_id)

	var $iframe = $('.cms_preview_iframe', $container)
	$iframe.attr('src', preview_url)

}

function cms_preview_init($root){

	var $scope = $root ? $root.find('.cms_preview_container') : $('.cms_preview_container')

	$scope.not('.cms_preview_ok').each(function(){
		$(this).addClass('cms_preview_ok')
	})

	$('.cms_preview_toggle').not('.cms_preview_toggle_ok').each(function(){

		var $btn = $(this)
		$btn.addClass('cms_preview_toggle_ok')

		$btn.on('click.cms', function(){
			if ($btn.hasClass('cms_preview_toggle_desktop')){
				cms_preview_open('desktop')
			} else if ($btn.hasClass('cms_preview_toggle_mobile')){
				cms_preview_open('mobile')
			}
		})

	})

	var mode = cms_preview_get_mode()
	cms_preview_update_toggle_ui(mode)

	if (mode){
		cms_preview_resize()
		cms_preview_load_iframe()
		cms_preview_resize_after_layout()
	}

}

$(document).ready(function(){

	cms_preview_init()

	$(window).on('resize.cms_preview', function(){
		cms_preview_resize()
	})

	cms_preview_resize()

})