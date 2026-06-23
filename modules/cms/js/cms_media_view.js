var cms_media_view_level_min = 0
var cms_media_view_level_max = 1
var cms_media_view_level_default = 0.5

function cms_media_view_level_clamp(value){

	var v = parseFloat(value)
	if (isNaN(v)){
		v = cms_media_view_level_default
	}
	v = Math.max(cms_media_view_level_min, Math.min(cms_media_view_level_max, v))
	return Math.round(v * 100) / 100

}

function cms_media_view_brightness_to_filter_amount(value){

	var ui = cms_media_view_level_clamp(value)
	var n_07 = 1 + (0.76 / 3)
	var n_09 = 1.76 + (0.01 / 0.2) * 18.24
	if (ui <= 0.5){
		return ui / 0.5
	}
	if (ui <= 0.7){
		return 1 + ((ui - 0.5) / 0.2) * (n_07 - 1)
	}
	if (ui <= 0.9){
		return n_07 + ((ui - 0.7) / 0.2) * (n_09 - n_07)
	}
	return n_09 + ((ui - 0.9) / 0.1) * (20 - n_09)

}

function cms_media_view_contrast_to_filter_amount(value){

	var ui = cms_media_view_level_clamp(value)
	if (ui <= 0.5){
		return ui / 0.5
	}
	if (ui <= 0.8){
		return 1 + ((ui - 0.5) / 0.3) * 0.6
	}
	return 1.6 + ((ui - 0.8) / 0.2) * 1.4

}

function cms_media_view_hex_to_rgb(hex){

	var match = /^#([0-9A-Fa-f]{6})$/.exec((hex || '').trim())
	if (!match){
		return null
	}

	return {
		r: parseInt(match[1].substr(0, 2), 16),
		g: parseInt(match[1].substr(2, 2), 16),
		b: parseInt(match[1].substr(4, 2), 16),
	}

}

function cms_media_view_root_px(){

	var fs = parseFloat($('html').css('font-size'))
	return isNaN(fs) ? 16 : fs

}

function cms_media_view_position_keywords(){

	return {
		left: 0,
		center: 50,
		right: 100,
		top: 0,
		bottom: 100,
	}

}

function cms_media_view_parse_position_axis(value){

	var keywords = cms_media_view_position_keywords()
	var v = (value || '').trim().toLowerCase()

	if (keywords[v] !== undefined){
		return { type: 'percent', value: keywords[v] }
	}

	if (v.indexOf('%') >= 0){
		return { type: 'percent', value: parseFloat(v) || 50 }
	}

	if (v.indexOf('px') >= 0){
		return { type: 'px', value: parseFloat(v) || 0 }
	}

	return { type: 'percent', value: 50 }

}

function cms_media_view_parse_background_position($host){

	var raw = ($host.css('background-position') || '50% 50%').split(/\s+/)
	var x = raw[0] || '50%'
	var y = raw[1] || raw[0] || '50%'

	return {
		x: cms_media_view_parse_position_axis(x),
		y: cms_media_view_parse_position_axis(y),
	}

}

function cms_media_view_position_offset(pos, host_size, fitted_size){

	if (!pos){
		return (host_size - fitted_size) / 2
	}

	if (pos.type === 'px'){
		return pos.value
	}

	return (host_size - fitted_size) * pos.value / 100

}

function cms_media_view_parse_size_token(token, host_size, root_px){

	if (!token){
		return null
	}

	var v = token.trim().toLowerCase()

	if (!v || v === 'auto'){
		return null
	}

	var match

	if (v === 'cover' || v === 'contain'){
		return v
	}

	if ((match = /^([\d.]+)rem$/.exec(v))){
		return parseFloat(match[1]) * root_px
	}

	if ((match = /^([\d.]+)px$/.exec(v))){
		return parseFloat(match[1])
	}

	if ((match = /^([\d.]+)%$/.exec(v))){
		return parseFloat(match[1]) / 100 * host_size
	}

	if ((match = /^([\d.]+)em$/.exec(v))){
		return parseFloat(match[1]) * root_px
	}

	return null

}

function cms_media_view_media_rule_matches(rule){

	if (!rule.media || !rule.media.mediaText){
		return true
	}

	try {
		return window.matchMedia(rule.media.mediaText).matches
	} catch (e){
		return true
	}

}

function cms_media_view_collect_background_size_rules(el, rules, matched){

	var i

	for (i = 0; i < rules.length; i++){

		var rule = rules[i]

		if (rule.cssRules && rule.type === CSSRule.MEDIA_RULE){
			if (cms_media_view_media_rule_matches(rule)){
				cms_media_view_collect_background_size_rules(el, rule.cssRules, matched)
			}
			continue
		}

		if (rule.cssRules && rule.type === CSSRule.SUPPORTS_RULE){
			cms_media_view_collect_background_size_rules(el, rule.cssRules, matched)
			continue
		}

		if (!rule.selectorText || !rule.style){
			continue
		}

		var selectors = rule.selectorText.split(',')
		var k

		for (k = 0; k < selectors.length; k++){

			var sel = selectors[k].trim()

			if (!sel){
				continue
			}

			try {
				if (el.matches(sel)){
					var bs = rule.style.backgroundSize || rule.style.getPropertyValue('background-size')
					if (bs){
						matched.push(bs)
					}
				}
			} catch (e){
			}

		}

	}

}

function cms_media_view_specified_background_size(el){

	if (!el){
		return ''
	}

	if (el.style && el.style.backgroundSize){
		return el.style.backgroundSize
	}

	var matched = []
	var sheets = document.styleSheets
	var i

	for (i = 0; i < sheets.length; i++){

		try {
			var rules = sheets[i].cssRules
			if (rules){
				cms_media_view_collect_background_size_rules(el, rules, matched)
			}
		} catch (e){
		}

	}

	if (matched.length){
		return matched[matched.length - 1]
	}

	return ''

}

function cms_media_view_parse_background_size($host, host_w, host_h){

	var root_px = cms_media_view_root_px()
	var el = $host[0]
	var cached = $host.data('cms_video_bg_size_raw')

	if (cached){
		var raw = cached
	} else {
		var inline = (el.style && el.style.backgroundSize) ? el.style.backgroundSize : ''
		var specified = cms_media_view_specified_background_size(el)
		raw = inline || specified || $host.css('background-size') || 'auto'
		$host.data('cms_video_bg_size_raw', raw)
	}

	var parts = raw.split(/\s+/).filter(function(p){ return p.length })
	var result = {
		fit: 'cover',
		size_w: null,
		size_h: null,
	}

	if (!parts.length){
		result.fit = 'contain'
		return result
	}

	if (parts.length === 1){

		var single = parts[0].trim().toLowerCase()

		if (single === 'cover'){
			return result
		}

		if (single === 'contain' || single === 'auto'){
			result.fit = 'contain'
			return result
		}

		var one = cms_media_view_parse_size_token(single, host_w, root_px)
		if (typeof one === 'number'){
			result.fit = 'explicit'
			result.size_w = one
		}

		return result

	}

	if (parts[0].trim() === '100%' && parts[1].trim() === '100%'){
		return result
	}

	var first = cms_media_view_parse_size_token(parts[0], host_w, root_px)
	var second = cms_media_view_parse_size_token(parts[1], host_h, root_px)

	if (typeof first === 'number' && second === null){
		result.fit = 'explicit'
		result.size_w = first
		return result
	}

	if (first === null && typeof second === 'number'){
		result.fit = 'explicit'
		result.size_h = second
		return result
	}

	if (typeof first === 'number' && typeof second === 'number'){

		if (/rem|%|em|auto/i.test(raw)){
			result.fit = 'explicit'
			result.size_w = first
			return result
		}

		result.fit = 'contain'
		return result

	}

	result.fit = 'contain'
	return result

}

function cms_media_view_measure_host($host){

	var rect = $host[0].getBoundingClientRect()
	var w = rect.width
	var h = rect.height

	if (h < 1){
		var pb = parseFloat($host.css('padding-bottom'))
		if (pb > 0 && w > 0){
			h = w * pb / 100
		}
	}

	if (w < 1){
		w = $host.outerWidth()
	}

	if (h < 1){
		h = $host.outerHeight()
	}

	return { w: w, h: h }

}

function cms_media_view_host_metrics($host){

	var dims = cms_media_view_measure_host($host)
	var size = cms_media_view_parse_background_size($host, dims.w, dims.h)

	return {
		w: dims.w,
		h: dims.h,
		fit: size.fit,
		size_w: size.size_w,
		size_h: size.size_h,
		position: cms_media_view_parse_background_position($host),
	}

}

function cms_media_view_read_meta($el){

	return {
		crop_x1: parseFloat($el.data('crop_x1')) || 0,
		crop_y1: parseFloat($el.data('crop_y1')) || 0,
		crop_x2: parseFloat($el.data('crop_x2')) || 100,
		crop_y2: parseFloat($el.data('crop_y2')) || 100,
		brightness: $el.data('brightness'),
		contrast: $el.data('contrast'),
		overlay_colour: $el.data('overlay_colour') || '#000000',
		overlay_opacity: $el.data('overlay_opacity'),
		rotation: parseFloat($el.data('rotation')) || 0,
		source_w: parseInt($el.data('source_w'), 10) || 0,
		source_h: parseInt($el.data('source_h'), 10) || 0,
	}

}

function cms_media_view_crop_dims(meta, host_w){

	var nw = meta.source_w
	var nh = meta.source_h

	if (!nw || !nh || !host_w){
		return null
	}

	var x1 = Math.min(meta.crop_x1, meta.crop_x2)
	var y1 = Math.min(meta.crop_y1, meta.crop_y2)
	var x2 = Math.max(meta.crop_x1, meta.crop_x2)
	var y2 = Math.max(meta.crop_y1, meta.crop_y2)

	var diagonal = Math.sqrt(nw * nw + nh * nh)
	var base_scale = host_w / diagonal
	var img_w = nw * base_scale
	var img_h = nh * base_scale

	var crop_l = x1 / 100 * img_w
	var crop_t = y1 / 100 * img_h
	var crop_w = Math.max((x2 - x1) / 100 * img_w, 1)
	var crop_h = Math.max((y2 - y1) / 100 * img_h, 1)

	return {
		img_w: img_w,
		img_h: img_h,
		crop_l: crop_l,
		crop_t: crop_t,
		crop_w: crop_w,
		crop_h: crop_h,
	}

}

function cms_media_view_layout(meta, host_w, host_h, host_fit){

	if (!host_fit){
		host_fit = {
			fit: 'cover',
			size_w: null,
			size_h: null,
			position: {
				x: { type: 'percent', value: 50 },
				y: { type: 'percent', value: 50 },
			},
		}
	}

	var crop = cms_media_view_crop_dims(meta, host_w)

	if (!crop || !host_w || !host_h){
		return null
	}

	var fit_scale
	var overlay_w
	var overlay_h
	var fit_mode = host_fit.fit

	if (fit_mode === 'explicit'){

		var crop_aspect = crop.crop_h / crop.crop_w
		var target_w
		var target_h

		if (host_fit.size_w != null){
			target_w = host_fit.size_w
			target_h = target_w * crop_aspect
		} else if (host_fit.size_h != null){
			target_h = host_fit.size_h
			target_w = target_h / crop_aspect
		} else {
			fit_mode = 'contain'
		}

		if (fit_mode === 'explicit'){
			fit_scale = target_w / crop.crop_w
			overlay_w = target_w
			overlay_h = target_h
		}

	}

	if (fit_mode === 'contain'){
		fit_scale = Math.min(host_w / crop.crop_w, host_h / crop.crop_h)
		overlay_w = crop.crop_w * fit_scale
		overlay_h = crop.crop_h * fit_scale
	} else if (fit_mode === 'cover'){
		fit_scale = Math.max(host_w / crop.crop_w, host_h / crop.crop_h)
		overlay_w = crop.crop_w * fit_scale
		overlay_h = crop.crop_h * fit_scale
	}

	var pan_w = crop.img_w * fit_scale
	var pan_h = crop.img_h * fit_scale
	var overlay_left = cms_media_view_position_offset(host_fit.position.x, host_w, overlay_w)
	var overlay_top = cms_media_view_position_offset(host_fit.position.y, host_h, overlay_h)
	var pan_inner_left = -crop.crop_l * fit_scale
	var pan_inner_top = -crop.crop_t * fit_scale

	return {
		crop_left: overlay_left,
		crop_top: overlay_top,
		crop_w: overlay_w,
		crop_h: overlay_h,
		pan_inner_left: pan_inner_left,
		pan_inner_top: pan_inner_top,
		pan_w: pan_w,
		pan_h: pan_h,
		overlay_left: overlay_left,
		overlay_top: overlay_top,
		overlay_w: overlay_w,
		overlay_h: overlay_h,
		origin_x: crop.crop_l * fit_scale + overlay_w / 2,
		origin_y: crop.crop_t * fit_scale + overlay_h / 2,
	}

}

function cms_media_view_ensure_crop_dom($host){

	var $crop = $host.find('.cms_video_view_crop')
	if ($crop.length){
		return
	}

	var $pan = $host.find('.cms_video_view_pan')
	if (!$pan.length){
		return
	}

	$crop = $('<div class="cms_video_view_crop">')
	$pan.detach().appendTo($crop)
	$host.find('.cms_video_view_clip').prepend($crop)

}

function cms_media_view_apply($host, meta, layout){

	if (!layout){
		return
	}

	cms_media_view_ensure_crop_dom($host)

	var b_n = cms_media_view_brightness_to_filter_amount(meta.brightness)
	var c_n = cms_media_view_contrast_to_filter_amount(meta.contrast)
	var deg = meta.rotation || 0

	$host.find('.cms_video_view_crop').css({
		left: layout.crop_left + 'px',
		top: layout.crop_top + 'px',
		width: layout.crop_w + 'px',
		height: layout.crop_h + 'px',
	})

	$host.find('.cms_video_view_pan').css({
		left: layout.pan_inner_left + 'px',
		top: layout.pan_inner_top + 'px',
		width: layout.pan_w + 'px',
		height: layout.pan_h + 'px',
	})

	$host.find('.cms_video_view_rotate').css({
		transform: deg ? 'rotate(' + deg + 'deg)' : '',
		'transform-origin': layout.origin_x + 'px ' + layout.origin_y + 'px',
	})

	$host.find('.cms_video_view_source').css({
		filter: 'brightness(' + b_n + ') contrast(' + c_n + ')',
	})

	var rgb = cms_media_view_hex_to_rgb(meta.overlay_colour)
	var opacity = cms_media_view_level_clamp(meta.overlay_opacity)
	var $overlay = $host.find('.cms_video_view_overlay')

	if (!$host.hasClass('cms_video_ready')){
		$overlay.css({
			display: 'none',
			'background-color': '',
		})
		return
	}

	if (!rgb || opacity <= 0.005){
		$overlay.css({
			display: 'none',
			'background-color': '',
		})
		return
	}

	$overlay.css({
		display: 'block',
		left: layout.overlay_left + 'px',
		top: layout.overlay_top + 'px',
		width: layout.overlay_w + 'px',
		height: layout.overlay_h + 'px',
		'background-color': 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + opacity + ')',
	})

}

function cms_media_view_relayout($host){

	var meta = cms_media_view_read_meta($host)
	var host_fit = cms_media_view_host_metrics($host)
	var layout = cms_media_view_layout(meta, host_fit.w, host_fit.h, host_fit)

	cms_media_view_apply($host, meta, layout)

	if (typeof cms_video_try_reveal === 'function'){
		cms_video_try_reveal($host)
	}

	return layout

}

function cms_media_view_background_position_css(position){

	if (!position){
		return '50% 50%'
	}

	var x = position.x
	var y = position.y
	var x_s = x.type === 'px' ? (x.value + 'px') : (x.value + '%')
	var y_s = y.type === 'px' ? (y.value + 'px') : (y.value + '%')

	return x_s + ' ' + y_s

}

function cms_media_view_plain_video_styles($style_host, video_w, video_h){

	var host_fit = cms_media_view_host_metrics($style_host)
	var position_css = cms_media_view_background_position_css(host_fit.position)
	var styles = {
		position: 'absolute',
		left: '50%',
		top: '50%',
		transform: 'translate(-50%, -50%)',
		'max-width': 'none',
		'max-height': 'none',
		'object-position': position_css,
	}

	if (host_fit.fit === 'cover'){
		styles.width = '100%'
		styles.height = '100%'
		styles['object-fit'] = 'cover'
		return styles
	}

	if (host_fit.fit === 'explicit'){

		var aspect = (video_w && video_h) ? (video_w / video_h) : 0
		var target_w = 0
		var target_h = 0

		if (host_fit.size_w != null){
			target_w = host_fit.size_w
			if (aspect){
				target_h = host_fit.size_w / aspect
			}
		} else if (host_fit.size_h != null){
			target_h = host_fit.size_h
			if (aspect){
				target_w = host_fit.size_h * aspect
			}
		}

		if (target_w < 1 && target_h < 1){
			styles.width = '100%'
			styles.height = '100%'
			styles['object-fit'] = 'fill'
			return styles
		}

		styles.width = target_w + 'px'
		if (target_h){
			styles.height = target_h + 'px'
		}

		styles.left = cms_media_view_position_offset(host_fit.position.x, host_fit.w, target_w) + 'px'
		styles.top = cms_media_view_position_offset(host_fit.position.y, host_fit.h, target_h) + 'px'
		styles.transform = ''
		return styles

	}

	styles.width = '100%'
	styles.height = '100%'
	styles['object-fit'] = 'contain'
	return styles

}