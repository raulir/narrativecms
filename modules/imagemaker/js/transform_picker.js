/**
 * Transform edge-point picker popup.
 * Layout: left ~square image stage, right tools (zoom / pan / select).
 * Zoom + pan apply to image and overlay together. Handles stay on image %.
 */

var transform_picker_zoom_min = 0.5
var transform_picker_zoom_max = 8.0

function transform_picker_round(v){
	return Math.round(v * 100) / 100
}

function transform_picker_clamp(v){
	return Math.round(Math.max(-20, Math.min(120, v)) * 100) / 100
}

function transform_picker_zoom_clamp(v){
	v = parseFloat(v)
	if (isNaN(v)){
		v = 1
	}
	return Math.max(transform_picker_zoom_min, Math.min(transform_picker_zoom_max, v))
}

function transform_picker_zoom_log_bounds(){
	return {
		min: Math.log2(transform_picker_zoom_min),
		max: Math.log2(transform_picker_zoom_max)
	}
}

function transform_picker_zoom_value_to_percent(value){
	var bounds = transform_picker_zoom_log_bounds()
	var z = transform_picker_zoom_clamp(value)
	return ((Math.log2(z) - bounds.min) / (bounds.max - bounds.min)) * 100
}

function transform_picker_zoom_percent_to_value(percent){
	var bounds = transform_picker_zoom_log_bounds()
	var log_val = bounds.min + (Math.max(0, Math.min(100, percent)) / 100) * (bounds.max - bounds.min)
	return transform_picker_zoom_clamp(Math.pow(2, log_val))
}

function transform_picker_default_grid(n){

	n = parseInt(n, 10) || 5
	if (n < 2){
		n = 5
	}
	var cells = n - 1
	var grid = []
	var y, x
	for (y = 0; y < n; y++){
		grid[y] = []
		for (x = 0; x < n; x++){
			grid[y][x] = [
				transform_picker_round((x / cells) * 100),
				transform_picker_round((y / cells) * 100)
			]
		}
	}
	return grid

}

function transform_picker_from_value(value_json, n){

	n = parseInt(n, 10) || 5
	var grid = transform_picker_default_grid(n)
	if (!value_json){
		return grid
	}
	var value
	try {
		value = typeof value_json === 'string' ? JSON.parse(value_json) : value_json
	} catch (e){
		return grid
	}
	if (!value || !value.data || !value.data.length){
		return grid
	}

	var data = value.data
	var top = data[0] || []
	var bottom = data[data.length - 1] || []
	var i, yi

	for (i = 0; i < n; i++){
		if (top[i]){
			grid[0][i] = [parseFloat(top[i][0]), parseFloat(top[i][1])]
		}
		if (bottom[i]){
			grid[n - 1][i] = [parseFloat(bottom[i][0]), parseFloat(bottom[i][1])]
		}
	}

	var mid = data.slice(1, -1)
	for (yi = 0; yi < mid.length; yi++){
		var row_i = yi + 1
		if (row_i >= n - 1){
			break
		}
		if (mid[yi] && mid[yi][0]){
			grid[row_i][0] = [parseFloat(mid[yi][0][0]), parseFloat(mid[yi][0][1])]
		}
		if (mid[yi] && mid[yi].length){
			var rp = mid[yi][mid[yi].length - 1]
			grid[row_i][n - 1] = [parseFloat(rp[0]), parseFloat(rp[1])]
		}
	}

	for (yi = 1; yi < data.length - 1; yi++){
		if (data[yi] && data[yi].length === n){
			for (i = 0; i < n; i++){
				grid[yi][i] = [parseFloat(data[yi][i][0]), parseFloat(data[yi][i][1])]
			}
		}
	}

	return grid

}

function transform_picker_to_value(grid, n){

	n = n || grid.length
	var cells = n - 1
	var data = []
	var i, y

	var top = []
	for (i = 0; i < n; i++){
		top.push([grid[0][i][0], grid[0][i][1]])
	}
	data.push(top)

	for (y = 1; y < n - 1; y++){
		data.push([
			[grid[y][0][0], grid[y][0][1]],
			[grid[y][n - 1][0], grid[y][n - 1][1]]
		])
	}

	var bottom = []
	for (i = 0; i < n; i++){
		bottom.push([grid[n - 1][i][0], grid[n - 1][i][1]])
	}
	data.push(bottom)

	return {
		width: cells,
		height: cells,
		maxx: 100,
		maxy: 100,
		units: 'percent',
		data: data
	}

}

function transform_picker_get_value($container){

	$container = $container && $container.length ? $container : $('.cms_transform_picker_container')
	var n = parseInt($container.data('points'), 10) || 5
	var grid = $container.data('grid')
	if (!grid){
		return transform_picker_to_value(transform_picker_default_grid(n), n)
	}
	return transform_picker_to_value(grid, n)

}

function transform_picker_handle_label(x, y, n){

	var last = n - 1
	if (y === 0){
		return 'T' + x
	}
	if (y === last){
		return 'B' + x
	}
	if (x === 0){
		return 'L' + y
	}
	if (x === last){
		return 'R' + y
	}
	return x + ',' + y

}

function transform_picker_is_corner(x, y, n){

	var last = n - 1
	return (x === 0 || x === last) && (y === 0 || y === last)

}

/**
 * Place intermediate edge points on the line between the two corner endpoints.
 * t = i/(n-1) → for n=5: 1/4, 1/2, 3/4.
 * edge: 'up' | 'right' | 'down' | 'left'
 */
function transform_picker_linearize_edge($container, edge){

	var n = parseInt($container.data('points'), 10) || 5
	var grid = $container.data('grid')
	if (!grid || n < 3){
		return
	}

	var last = n - 1
	var a, b
	var i, t

	if (edge === 'up'){
		a = grid[0][0]
		b = grid[0][last]
		for (i = 1; i < last; i++){
			t = i / last
			grid[0][i] = [
				transform_picker_round(a[0] + t * (b[0] - a[0])),
				transform_picker_round(a[1] + t * (b[1] - a[1]))
			]
		}
	} else if (edge === 'right'){
		a = grid[0][last]
		b = grid[last][last]
		for (i = 1; i < last; i++){
			t = i / last
			grid[i][last] = [
				transform_picker_round(a[0] + t * (b[0] - a[0])),
				transform_picker_round(a[1] + t * (b[1] - a[1]))
			]
		}
	} else if (edge === 'down'){
		a = grid[last][0]
		b = grid[last][last]
		for (i = 1; i < last; i++){
			t = i / last
			grid[last][i] = [
				transform_picker_round(a[0] + t * (b[0] - a[0])),
				transform_picker_round(a[1] + t * (b[1] - a[1]))
			]
		}
	} else if (edge === 'left'){
		a = grid[0][0]
		b = grid[last][0]
		for (i = 1; i < last; i++){
			t = i / last
			grid[i][0] = [
				transform_picker_round(a[0] + t * (b[0] - a[0])),
				transform_picker_round(a[1] + t * (b[1] - a[1]))
			]
		}
	} else {
		return
	}

	$container.data('grid', grid)
	transform_picker_draw($container)

}

/** Page coords → % of stage (works with pan + zoom via getBoundingClientRect) */
function transform_picker_page_to_percent($stage, page_x, page_y){

	var el = $stage[0]
	if (!el){
		return { x: 0, y: 0 }
	}
	var rect = el.getBoundingClientRect()
	var w = rect.width || 1
	var h = rect.height || 1
	// pageX/Y vs client: getBoundingClientRect is viewport-relative
	var cx = page_x - (window.pageXOffset || 0)
	var cy = page_y - (window.pageYOffset || 0)
	// Prefer clientX if available via event — callers pass page; convert
	// Actually mousemove has both; use client coords if we pass them
	return {
		x: transform_picker_clamp(((page_x - rect.left - (window.scrollX || window.pageXOffset || 0)) / w) * 100),
		y: transform_picker_clamp(((page_y - rect.top - (window.scrollY || window.pageYOffset || 0)) / h) * 100)
	}

}

function transform_picker_client_to_percent($stage, client_x, client_y){

	var el = $stage[0]
	if (!el){
		return { x: 0, y: 0 }
	}
	var rect = el.getBoundingClientRect()
	var w = rect.width || 1
	var h = rect.height || 1
	return {
		x: transform_picker_clamp(((client_x - rect.left) / w) * 100),
		y: transform_picker_clamp(((client_y - rect.top) / h) * 100)
	}

}

function transform_picker_get_zoom($container){
	return transform_picker_zoom_clamp($container.data('zoom') || 1)
}

function transform_picker_get_pan($container){
	return {
		x: parseFloat($container.data('pan_x')) || 0,
		y: parseFloat($container.data('pan_y')) || 0
	}
}

function transform_picker_apply_view($container){

	var zoom = transform_picker_get_zoom($container)
	var pan = transform_picker_get_pan($container)
	var $stage = $('.cms_transform_picker_stage', $container)
	var $pan = $('.cms_transform_picker_pan', $container)

	// Pan only — no CSS scale (that would enlarge handles/tooltips)
	$pan.css({
		transform: 'translate(' + pan.x + 'px, ' + pan.y + 'px)'
	})

	// Zoom by resizing stage (fit size × zoom). Overlay % coords track the image;
	// handle boxes stay rem-sized (unscaled HTML).
	var fit_w = parseFloat($container.data('fit_w')) || 0
	var fit_h = parseFloat($container.data('fit_h')) || 0
	if (fit_w > 0 && fit_h > 0 && $stage.length){
		$stage.css({
			width: (fit_w * zoom) + 'px',
			height: (fit_h * zoom) + 'px',
			left: '50%',
			top: '50%',
			transform: 'translate(-50%, -50%)'
		})
	}

	$('.cms_transform_picker_zoom_input', $container).val(zoom.toFixed(1))
	$('.cms_transform_picker_zoom_slider_handle', $container).css({
		left: transform_picker_zoom_value_to_percent(zoom) + '%'
	})

}

/**
 * Work-area size + optional client point → coords in the pan/content space
 * (origin top-left of the area padding box, matching pan translate).
 * @param {jQuery} $area
 * @param {{clientX:number,clientY:number}|null} [focus]
 * @returns {{aw:number,ah:number,cx:number,cy:number}}
 */
function transform_picker_zoom_focus($area, focus){

	var el = $area && $area[0]
	if (!el){
		return { aw: 1, ah: 1, cx: 0.5, cy: 0.5 }
	}

	// clientWidth/Height = padding box (absolute children / pan live here)
	var aw = el.clientWidth || 1
	var ah = el.clientHeight || 1
	// Default: centre of work area (slider / input / cursor outside)
	var cx = aw / 2
	var cy = ah / 2

	if (focus && typeof focus.clientX === 'number' && typeof focus.clientY === 'number'){
		var rect = el.getBoundingClientRect()
		var cs = window.getComputedStyle(el)
		var bl = parseFloat(cs.borderLeftWidth) || 0
		var bt = parseFloat(cs.borderTopWidth) || 0
		var fx = focus.clientX - rect.left - bl
		var fy = focus.clientY - rect.top - bt
		// Cursor over the work area → keep that image point fixed
		if (fx >= 0 && fy >= 0 && fx <= aw && fy <= ah){
			cx = fx
			cy = fy
		}
	}

	return { aw: aw, ah: ah, cx: cx, cy: cy }

}

/**
 * @param {object} $container
 * @param {number} value new zoom
 * @param {boolean} [keep_input]
 * @param {{clientX:number,clientY:number}|null} [focus]
 *   Viewport point that should stay fixed (wheel under cursor).
 *   If omitted / outside work area, zoom toward centre of the work area.
 */
function transform_picker_set_zoom($container, value, keep_input, focus){

	var z0 = transform_picker_get_zoom($container)
	var z1 = transform_picker_zoom_clamp(value)
	if (z1 === z0){
		if (!keep_input){
			$('.cms_transform_picker_zoom_input', $container).val(z1.toFixed(1))
		}
		transform_picker_apply_view($container)
		return
	}

	var $area = $('.cms_transform_picker_area', $container)
	var f = transform_picker_zoom_focus($area, focus)
	var pan = transform_picker_get_pan($container)
	var ratio = z1 / z0

	// Stage is centered in the pan box; pan is translate on the pan layer.
	// Keep the image point under (cx,cy) fixed while zoom changes:
	//   screen = centre + pan + content * z
	//   pan1 = cx - centre - (z1/z0) * (cx - centre - pan0)
	$container.data('pan_x', f.cx - f.aw / 2 - ratio * (f.cx - f.aw / 2 - pan.x))
	$container.data('pan_y', f.cy - f.ah / 2 - ratio * (f.cy - f.ah / 2 - pan.y))
	$container.data('zoom', z1)
	if (!keep_input){
		$('.cms_transform_picker_zoom_input', $container).val(z1.toFixed(1))
	}
	transform_picker_apply_view($container)

}

function transform_picker_set_pan($container, x, y){

	$container.data('pan_x', x)
	$container.data('pan_y', y)
	transform_picker_apply_view($container)

}

function transform_picker_reset_view($container){

	$container.data('zoom', 1)
	$container.data('pan_x', 0)
	$container.data('pan_y', 0)
	$('.cms_transform_picker_zoom_input', $container).val('1.0')
	transform_picker_apply_view($container)

}

function transform_picker_layout_stage($container){

	var $area = $('.cms_transform_picker_area', $container)
	var $stage = $('.cms_transform_picker_stage', $container)
	if (!$stage.length || !$stage.data('h')){
		return
	}

	var aw = $area.innerWidth()
	var ah = $area.innerHeight()
	var nw = parseFloat($stage.data('w')) || 1
	var nh = parseFloat($stage.data('h')) || 1
	var ratio = nw / nh
	var ar = aw / ah
	var sw, sh

	// Fit size at zoom 1 (contain); zoom multiplies these px sizes
	if (ratio > ar){
		sw = aw
		sh = aw / ratio
	} else {
		sh = ah
		sw = ah * ratio
	}

	$container.data('fit_w', sw)
	$container.data('fit_h', sh)

	// Background image URL from data-image on container
	var img = $container.attr('data-image') || $container.data('image') || ''
	if (img && typeof _cms_base !== 'undefined'){
		$('.cms_transform_picker_image_bg', $stage).css({
			'background-image': 'url(' + _cms_base + 'img/' + img + ')'
		})
	}

	transform_picker_apply_view($container)

}

function transform_picker_draw($container){

	var grid = $container.data('grid')
	var n = parseInt($container.data('points'), 10) || 5
	if (!grid){
		return
	}

	var $svg = $('.cms_transform_picker_svg', $container)
	var $handles = $('.cms_transform_picker_handles', $container)
	if (!$svg.length || !$handles.length){
		return
	}

	var ring = []
	var i, y
	for (i = 0; i < n; i++){
		ring.push(grid[0][i])
	}
	for (y = 1; y < n - 1; y++){
		ring.push(grid[y][n - 1])
	}
	for (i = n - 1; i >= 0; i--){
		ring.push(grid[n - 1][i])
	}
	for (y = n - 2; y >= 1; y--){
		ring.push(grid[y][0])
	}

	var points_attr = ring.map(function(p){
		return p[0] + ',' + p[1]
	}).join(' ')
	if (ring.length){
		points_attr += ' ' + ring[0][0] + ',' + ring[0][1]
	}

	$svg.empty()
	var poly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline')
	poly.setAttribute('points', points_attr)
	$svg[0].appendChild(poly)

	$handles.empty()
	for (y = 0; y < n; y++){
		for (i = 0; i < n; i++){
			if (y > 0 && y < n - 1 && i > 0 && i < n - 1){
				continue
			}
			var p = grid[y][i]
			var label = transform_picker_handle_label(i, y, n)
			var $h = $('<div class="cms_transform_picker_handle"></div>')
			if (transform_picker_is_corner(i, y, n)){
				$h.addClass('cms_transform_picker_handle_corner')
			}
			$h.css({ left: p[0] + '%', top: p[1] + '%' })
			$h.attr('data-gx', i)
			$h.attr('data-gy', y)
			$h.attr('title', label + ': ' + p[0] + ', ' + p[1])
			$h.append(
				$('<div class="cms_transform_picker_tooltip"></div>').text(label + '  ' + p[0] + ', ' + p[1])
			)
			$handles.append($h)
		}
	}

	transform_picker_bind_handles($container)

}

function transform_picker_draw_polyline($container){

	var grid = $container.data('grid')
	var n = parseInt($container.data('points'), 10) || 5
	if (!grid){
		return
	}
	var ring = []
	var i, y
	for (i = 0; i < n; i++){
		ring.push(grid[0][i])
	}
	for (y = 1; y < n - 1; y++){
		ring.push(grid[y][n - 1])
	}
	for (i = n - 1; i >= 0; i--){
		ring.push(grid[n - 1][i])
	}
	for (y = n - 2; y >= 1; y--){
		ring.push(grid[y][0])
	}
	var points_attr = ring.map(function(p){
		return p[0] + ',' + p[1]
	}).join(' ')
	if (ring.length){
		points_attr += ' ' + ring[0][0] + ',' + ring[0][1]
	}
	var $poly = $('.cms_transform_picker_svg polyline', $container)
	if ($poly.length){
		$poly.attr('points', points_attr)
	}

}

function transform_picker_bind_handles($container){

	var $stage = $('.cms_transform_picker_stage', $container)
	var n = parseInt($container.data('points'), 10) || 5

	$('.cms_transform_picker_handle', $container).off('mousedown.cms_transform').on('mousedown.cms_transform', function(e){

		e.preventDefault()
		e.stopPropagation()

		var $handle = $(this)
		var gx = parseInt($handle.attr('data-gx'), 10)
		var gy = parseInt($handle.attr('data-gy'), 10)
		$handle.addClass('cms_transform_picker_handle_active')

		function on_move(ev){
			var pct = transform_picker_client_to_percent($stage, ev.clientX, ev.clientY)
			var grid = $container.data('grid')
			if (!grid){
				return
			}
			grid[gy][gx] = [pct.x, pct.y]
			$container.data('grid', grid)

			var label = transform_picker_handle_label(gx, gy, n)
			$handle.css({ left: pct.x + '%', top: pct.y + '%' })
			$handle.find('.cms_transform_picker_tooltip').text(label + '  ' + pct.x + ', ' + pct.y)
			$handle.attr('title', label + ': ' + pct.x + ', ' + pct.y)

			transform_picker_draw_polyline($container)
		}

		function on_up(){
			$(document).off('mousemove.cms_transform_handle mouseup.cms_transform_handle')
			$handle.removeClass('cms_transform_picker_handle_active')
			transform_picker_draw($container)
		}

		$(document).on('mousemove.cms_transform_handle', on_move)
		$(document).on('mouseup.cms_transform_handle', on_up)

	})

}

function transform_picker_bind_pan_zoom($container){

	var $area = $('.cms_transform_picker_area', $container)

	// Pan: drag empty area (not handles)
	$area.off('mousedown.cms_transform_pan').on('mousedown.cms_transform_pan', function(e){

		if ($(e.target).closest('.cms_transform_picker_handle').length){
			return
		}
		if (!$('.cms_transform_picker_stage', $container).length){
			return
		}

		e.preventDefault()
		var start_x = e.clientX
		var start_y = e.clientY
		var pan0 = transform_picker_get_pan($container)
		$area.addClass('cms_transform_picker_area_panning')

		function on_move(ev){
			var dx = ev.clientX - start_x
			var dy = ev.clientY - start_y
			transform_picker_set_pan($container, pan0.x + dx, pan0.y + dy)
		}

		function on_up(){
			$(document).off('mousemove.cms_transform_pan mouseup.cms_transform_pan')
			$area.removeClass('cms_transform_picker_area_panning')
		}

		$(document).on('mousemove.cms_transform_pan', on_move)
		$(document).on('mouseup.cms_transform_pan', on_up)

	})

	// Wheel zoom — keep point under cursor fixed (native listener so preventDefault works)
	var area_el = $area[0]
	if (area_el){
		if (area_el._cms_transform_wheel){
			area_el.removeEventListener('wheel', area_el._cms_transform_wheel)
		}
		area_el._cms_transform_wheel = function(e){
			e.preventDefault()
			var z = transform_picker_get_zoom($container)
			var factor = (e.deltaY < 0) ? 1.1 : (1 / 1.1)
			transform_picker_set_zoom($container, z * factor, false, {
				clientX: e.clientX,
				clientY: e.clientY
			})
		}
		area_el.addEventListener('wheel', area_el._cms_transform_wheel, { passive: false })
	}

	// Zoom input / slider — cursor outside image → zoom toward work-area centre
	$('.cms_transform_picker_zoom_input', $container).off('change.cms blur.cms').on('change.cms blur.cms', function(){
		transform_picker_set_zoom($container, $(this).val(), false, null)
	})

	var $slider = $('.cms_transform_picker_zoom_slider_inner', $container)
	$slider.off('mousedown.cms_transform_zoom').on('mousedown.cms_transform_zoom', function(e){

		e.preventDefault()
		function set_from_event(ev){
			var offset = $slider.offset()
			var w = $slider.width() || 1
			var percent = ((ev.pageX - offset.left) / w) * 100
			// null focus → zoom about work-area centre
			transform_picker_set_zoom($container, transform_picker_zoom_percent_to_value(percent), false, null)
		}
		set_from_event(e)
		function on_move(ev){
			set_from_event(ev)
		}
		function on_up(){
			$(document).off('mousemove.cms_transform_zoom mouseup.cms_transform_zoom')
		}
		$(document).on('mousemove.cms_transform_zoom', on_move)
		$(document).on('mouseup.cms_transform_zoom', on_up)

	})

	$('.cms_transform_picker_zoom_reset', $container).off('click.cms').on('click.cms', function(e){
		e.preventDefault()
		transform_picker_reset_view($container)
	})

	$('.cms_transform_picker_edge', $container).off('click.cms_edge').on('click.cms_edge', function(e){
		e.preventDefault()
		transform_picker_linearize_edge($container, $(this).attr('data-edge'))
	})

}

function transform_picker_init($root){

	if ($root && $root.length && $root.hasClass('cms_transform_picker_ok')){
		return
	}

	cms_popup_run('transform_picker', function($popup){

		var $container = $popup
			? $popup.find('.cms_transform_picker_container').addBack('.cms_transform_picker_container').first()
			: $('.cms_transform_picker_container')

		if (!$container.length || $container.hasClass('cms_transform_picker_ok')){
			return
		}
		$container.addClass('cms_transform_picker_ok')

		var n = parseInt($container.data('points'), 10) || 5
		var grid = transform_picker_from_value($container.attr('data-value') || '', n)
		$container.data('grid', grid)
		$container.data('zoom', 1)
		$container.data('pan_x', 0)
		$container.data('pan_y', 0)

		setTimeout(function(){
			transform_picker_layout_stage($container)
			transform_picker_draw($container)
			transform_picker_bind_pan_zoom($container)
		}, 50)

		$('.cms_transform_picker_reset', $container).off('click.cms_reset').on('click.cms_reset', function(e){
			e.preventDefault()
			$container.data('grid', transform_picker_default_grid(n))
			transform_picker_draw($container)
		})

	})

}

function transform_picker_resize(){

	var $c = $('.cms_transform_picker_container.cms_transform_picker_ok')
	if (!$c.length){
		return
	}
	transform_picker_layout_stage($c)
	transform_picker_draw($c)

}

$(document).ready(function(){
	$(window).on('resize.cms', transform_picker_resize)
	transform_picker_init()
})
