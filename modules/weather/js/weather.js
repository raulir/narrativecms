'use strict'

var WEATHER_LINE = '#5CE1FF'
var WEATHER_LINE_MIN = 'rgba(154, 172, 196, 0.85)'
var WEATHER_GRID = 'rgba(92, 225, 255, 0.18)'
var WEATHER_LABEL = '#9aacc4'
var WEATHER_AXIS = 'rgba(232, 238, 248, 0.85)'
var WEATHER_DAY_SEP = 'rgba(92, 225, 255, 0.55)'
var WEATHER_REFRESH_DEFAULT_SEC = 180
var weather_refresh_timers = {}

// Match weather_model::sky_glyph — emoji only, no CSS pseudo icons
function weather_sky_glyph(sky){

	var map = {
		clear: '☀️',
		clear_night: '🌙',
		partly: '⛅',
		partly_night: '🌙',
		cloudy: '☁️',
		drizzle: '☁️', // base; fog overlay via weather_sky_overlay
		fog: '🌫️',
		rain: '🌧️',
		rain_heavy: '🌧️',
		showers: '🌦️',
		snow: '❄️',
		thunder: '⚡',
		later: '···',
		unknown: '☁️'
	}
	return map[sky] || map.cloudy

}

/** Overlay key for complex icons. Matches weather_model::sky_overlay */
function weather_sky_overlay(sky){

	if (sky === 'drizzle'){
		return 'fog'
	}
	if (sky === 'partly_night'){
		return 'cloud'
	}
	return null

}

function weather_sky_overlay_glyph(overlay){

	if (overlay === 'fog'){
		return '🌫️'
	}
	if (overlay === 'cloud'){
		return '☁️'
	}
	return ''

}

/** Condition slug for .weather_sky_stack_* — matches weather_model::sky_stack_slug */
function weather_sky_stack_slug(sky){

	var map = {
		clear: 'full_sun',
		clear_night: 'full_moon',
		partly: 'partial_sun',
		partly_night: 'partial_moon',
		cloudy: 'cloudy',
		drizzle: 'drizzle',
		fog: 'fog',
		rain: 'rain',
		rain_heavy: 'rain_heavy',
		showers: 'showers',
		snow: 'snow',
		thunder: 'thunder',
		later: 'later',
		unknown: 'unknown'
	}
	return map[sky] || 'unknown'

}

/**
 * Stacked sky HTML: base + optional overlay (matches weather_model::sky_html).
 * Always includes weather_sky_stack_{slug} for per-condition CSS.
 * @param {string} sky
 * @param {string} [glyph] base glyph override
 * @param {string|null} [overlay] overlay key or null
 * @param {string} [overlay_glyph] overlay glyph override
 * @param {string} [stack_slug] override slug (from API sky_stack)
 */
function weather_sky_html(sky, glyph, overlay, overlay_glyph, stack_slug){

	sky = sky || 'cloudy'
	var g = glyph || weather_sky_glyph(sky)
	var slug = stack_slug || weather_sky_stack_slug(sky)
	var stack_cls = 'weather_sky_stack weather_sky_stack_' + slug
	var ov = (overlay !== undefined && overlay !== null && overlay !== '')
		? overlay
		: weather_sky_overlay(sky)
	if (!ov){
		return '<span class="' + stack_cls + '"><span class="weather_sky_base">' + g + '</span></span>'
	}
	var og = overlay_glyph || weather_sky_overlay_glyph(ov)
	return '<span class="' + stack_cls + ' weather_sky_stack_has_overlay">'
		+ '<span class="weather_sky_base">' + g + '</span>'
		+ '<span class="weather_sky_overlay">' + og + '</span>'
		+ '</span>'

}

function weather_api_base(){
	return (typeof _cms_base !== 'undefined' ? _cms_base : '/')
}

function weather_init($root){

	var $scope = $root ? $root.find('.weather_container') : $('.weather_container')

	$scope.not('.weather_ok').each(function(){

		var $el = $(this)
		$el.addClass('weather_ok')

		var days = []
		var later = []
		var hours = {}
		try {
			days = JSON.parse($el.find('.weather_days_data').text() || '[]')
		} catch (e1) {
			days = []
		}
		try {
			later = JSON.parse($el.find('.weather_later_data').text() || '[]')
		} catch (e2) {
			later = []
		}
		try {
			hours = JSON.parse($el.find('.weather_hours_data').text() || '{}')
		} catch (e3) {
			hours = {}
		}

		var state = {
			days: days,
			later: later,
			hours: hours,
			selected: $el.data('selected') || (days[0] && days[0].date) || '',
			mode: 'day' // day | later
		}

		$el.data('weather_state', state)

		// Rebuild chips from JSON so SSR and refresh share one markup path
		// (avoids first-paint stack layout mismatches on day icons)
		weather_rebuild_day_chips($el, state)

		$el.on('click.weather', '.weather_day', function(e){
			e.preventDefault()
			var st = $el.data('weather_state')
			if (!st){
				return
			}
			var date = $(this).data('date')
			if (!date){
				return
			}
			$el.find('.weather_day').removeClass('weather_day_on').attr('aria-selected', 'false')
			$(this).addClass('weather_day_on').attr('aria-selected', 'true')
			if (date === 'later'){
				st.mode = 'later'
				st.selected = 'later'
			} else {
				st.mode = 'day'
				st.selected = String(date)
			}
			$el.data('weather_state', st)
			weather_render($el)
		})

		$(window).on('resize.weather', function(){
			weather_render($el)
		})

		weather_render($el)
		// Big refresh owned by header menu (menu_menu.js → weather/refresh_weather).
		// Soft-apply when menu poll returns and this panel is mounted.

	})

}

function weather_schedule_refresh($el){

	var id = 'weather'
	if (weather_refresh_timers[id]){
		return
	}

	var sec = parseInt($el.data('refresh_seconds'), 10)
	if (!isFinite(sec) || sec < 60){
		sec = WEATHER_REFRESH_DEFAULT_SEC
	}

	function tick(){
		weather_refresh_timers[id] = setTimeout(function(){
			weather_do_refresh($el, function(){
				tick()
			})
		}, sec * 1000)
	}

	weather_refresh_timers[id] = setTimeout(function(){
		weather_do_refresh($el, function(){
			tick()
		})
	}, 5000)

}

function weather_do_refresh($el, done){

	done = done || function(){}

	$.ajax({
		type: 'POST',
		url: weather_api_base() + 'weather/refresh_weather',
		dataType: 'json',
		timeout: 60000,
		success: function(data){
			if (data && data.ok && data.days && data.days.length){
				weather_apply_refresh_data($el, data)
			}
			done()
		},
		error: function(){
			done()
		}
	})

}

function weather_apply_refresh_data($el, data){

	var state = $el.data('weather_state')
	if (!state){
		return
	}

	var prev_sel = state.selected
	var prev_mode = state.mode

	state.days = data.days || []
	state.later = data.later_days || []
	state.hours = data.hours_by_date || {}

	// Keep selection if still valid
	if (prev_mode === 'later' && state.later.length){
		state.mode = 'later'
		state.selected = 'later'
	} else {
		var still = false
		var i
		for (i = 0; i < state.days.length; i++){
			if (state.days[i].date === prev_sel){
				still = true
				break
			}
		}
		state.mode = 'day'
		state.selected = still ? prev_sel : (state.days[0] ? state.days[0].date : '')
	}

	$el.data('weather_state', state)

	try {
		$el.find('.weather_days_data').text(JSON.stringify(state.days))
		$el.find('.weather_later_data').text(JSON.stringify(state.later))
		$el.find('.weather_hours_data').text(JSON.stringify(state.hours))
	} catch (e) { /* ignore */ }

	weather_rebuild_day_chips($el, state)
	weather_render($el)

}

function weather_rebuild_day_chips($el, state){

	var $row = $el.find('.weather_days')
	if (!$row.length){
		return
	}

	var label_later = $el.data('label_later') || 'Later'
	var html = ''
	var i
	for (i = 0; i < state.days.length; i++){
		var day = state.days[i]
		var on = (state.mode === 'day' && state.selected === day.date) ? ' weather_day_on' : ''
		var tmax = day.temp_max != null ? (day.temp_max + '°') : '–'
		var tmin = day.temp_min != null ? (day.temp_min + '°') : '–'
		html += '<button type="button" class="weather_day' + on + '" data-date="' + day.date + '" role="tab">'
		html += '<span class="weather_day_label">' + (day.label_chip || '') + '</span>'
		html += '<span class="weather_day_icon weather_sky" aria-hidden="true">' +
			weather_sky_html(day.sky || 'cloudy', day.sky_glyph, day.sky_overlay, day.sky_overlay_glyph, day.sky_stack) + '</span>'
		html += '<span class="weather_day_temps"><span class="weather_day_max">' + tmax + '</span>'
		html += '<span class="weather_day_min">' + tmin + '</span></span></button>'
	}

	var later_on = (state.mode === 'later') ? ' weather_day_on' : ''
	var later_n = state.later && state.later.length ? state.later.length + 'd' : '–'
	html += '<button type="button" class="weather_day weather_day_later' + later_on + '" data-date="later" role="tab">'
	html += '<span class="weather_day_label">' + label_later + '</span>'
	html += '<span class="weather_day_icon weather_sky" aria-hidden="true">' + weather_sky_html('later') + '</span>'
	html += '<span class="weather_day_temps weather_day_temps_later"><span class="weather_day_max">' + later_n + '</span>'
	html += '<span class="weather_day_min">more</span></span></button>'

	$row.html(html)

}

function weather_render($el){

	var state = $el.data('weather_state')
	if (!state){
		return
	}

	var $day_panel = $el.find('.weather_detail_day')
	var $later_panel = $el.find('.weather_detail_later')

	if (state.mode === 'later'){
		$day_panel.attr('hidden', true)
		$later_panel.removeAttr('hidden')
		weather_render_later($el, state)
	} else {
		$later_panel.attr('hidden', true)
		$day_panel.removeAttr('hidden')
		weather_render_day($el, state)
	}

}

function weather_render_day($el, state){

	var date = state.selected
	var slots = state.hours[date] || []
	if (slots.length < 24){
		var filled = []
		for (var h = 0; h < 24; h++){
			filled.push(slots[h] || { hour: h, missing: 1, temp_c: null })
		}
		slots = filled
	} else if (slots.length > 24){
		slots = slots.slice(0, 24)
	}

	var $band = $el.find('.weather_temp_band')
	var $times = $el.find('.weather_hour_times')
	var $winds = $el.find('.weather_hour_winds')
	$band.empty()
	$times.empty()
	$winds.empty()

	// Day temp range for vertical placement — full degrees only so same label → same height
	var temps = []
	var i
	for (i = 0; i < slots.length; i++){
		if (slots[i] && slots[i].temp_c != null && isFinite(+slots[i].temp_c) && !slots[i].missing){
			temps.push(Math.round(+slots[i].temp_c))
		}
	}
	var tmin = temps.length ? Math.min.apply(null, temps) : 0
	var tmax = temps.length ? Math.max.apply(null, temps) : 8
	if (tmax <= tmin){
		tmax = tmin + 1
	}
	// Minimum 8°C span so a flat winter day (e.g. 1–4°C) does not stretch oddly
	var WEATHER_TEMP_SPAN_MIN = 8
	var span = tmax - tmin
	if (span < WEATHER_TEMP_SPAN_MIN){
		var mid = (tmin + tmax) / 2
		// Keep integer degree steps after expanding flat days
		tmin = Math.round(mid - WEATHER_TEMP_SPAN_MIN / 2)
		tmax = tmin + WEATHER_TEMP_SPAN_MIN
	}
	// Small integer headroom so cards near extremes are not clipped
	var tpad = Math.max(1, Math.round((tmax - tmin) * 0.06))
	tmin -= tpad
	tmax += tpad

	var rem = (typeof _cms_rem === 'number' && _cms_rem > 0)
		? _cms_rem
		: parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 14
	// temp + icon (2.4rem) + margins 0.2+0.2 + pop + gaps
	var card_h = 5.8 * rem
	var $cards = []

	for (i = 0; i < 24; i++){
		var sl = slots[i] || { hour: i, missing: 1 }
		var hour = (sl.hour != null) ? +sl.hour : i
		var hh = (hour < 10 ? '0' : '') + hour

		var $col = $('<div class="weather_hour_col"></div>')
		if (!sl.missing && sl.temp_c != null && isFinite(+sl.temp_c)){
			// Same rounded ° as label → shared vertical band
			var t = Math.round(+sl.temp_c)
			var $card = $('<div class="weather_hour_card"></div>')
			$card.append($('<div class="weather_hour_card_temp"></div>').text(t + '°'))

			var sky = sl.sky || 'unknown'
			// Per-condition class matches stack slug (full_sun, partial_moon, drizzle, …)
			// so complex icons can be positioned separately from simple moon/sun
			var slug = sl.sky_stack || weather_sky_stack_slug(sky)
			var sky_mod = ' weather_hour_card_sky_' + slug
			$card.append(
				$('<div class="weather_hour_card_sky' + sky_mod + '" aria-hidden="true"></div>')
					.html(weather_sky_html(sky, sl.sky_glyph, sl.sky_overlay, sl.sky_overlay_glyph, slug))
			)

			var pop = (sl.precip_prob != null && isFinite(+sl.precip_prob)) ? Math.round(+sl.precip_prob) : 0
			// Always reserve pop row so icons align; hide label under 5%
			var $pop = $('<div class="weather_hour_card_pop"></div>').text(pop + '%')
			if (pop < 5){
				$pop.addClass('weather_hour_card_pop_hidden')
			} else if (pop >= 50){
				$pop.addClass('weather_hour_card_pop_high')
			} else if (pop >= 10){
				$pop.addClass('weather_hour_card_pop_mid')
			}
			$card.append($pop)
			$col.append($card)
			$cards.push({ $card: $card, t: t })
		}
		$band.append($col)

		var $tcol = $('<div class="weather_hour_time_col"></div>')
		$tcol.append($('<div class="weather_hour_time"></div>').text(hh + ':00'))
		$times.append($tcol)

		var $wcol = $('<div class="weather_hour_wind_col"></div>')
		$wcol.append(weather_wind_el(sl))
		if (sl.wind_ms != null && !sl.missing){
			// wind_ms field stores mph after Open-Meteo unit change
			$wcol.append($('<div class="weather_hour_mph"></div>').text(String(Math.round(+sl.wind_ms))))
		}
		$winds.append($wcol)
	}

	// Position cards after layout (band has real height)
	var place = function(){
		var band_h = $band.height() || 200
		var usable = Math.max(40, band_h - card_h)
		var j
		for (j = 0; j < $cards.length; j++){
			var frac = ($cards[j].t - tmin) / (tmax - tmin)
			if (frac < 0) frac = 0
			if (frac > 1) frac = 1
			// Higher temp → higher on screen
			$cards[j].$card.css('top', ((1 - frac) * usable) + 'px')
		}
	}
	place()
	// Second frame in case flex height settles late
	if (typeof requestAnimationFrame === 'function'){
		requestAnimationFrame(place)
	}

}

function weather_render_later($el, state){

	var later = state.later || []
	// Flatten 6h blocks across later days
	var blocks = []
	for (var d = 0; d < later.length; d++){
		var day = later[d]
		var b6 = day.blocks_6h || []
		for (var b = 0; b < b6.length; b++){
			var block = b6[b]
			blocks.push({
				day: day,
				block: block,
				day_start: b === 0,
				date: day.date,
				label_day: day.label_chip || (day.label_dow + ' ' + (day.label_day || '')),
				hour_start: block.hour_start
			})
		}
	}

	weather_draw_later_canvas($el.find('.weather_later_canvas').get(0), later)

	var $row = $el.find('.weather_later_hours')
	$row.empty()

	if (!blocks.length){
		$row.append($('<div class="weather_message"></div>').text('No further days'))
		return
	}

	for (var i = 0; i < blocks.length; i++){
		var item = blocks[i]
		var bl = item.block
		var $col = $('<div class="weather_later_slot"></div>')
		if (item.day_start){
			$col.addClass('weather_later_day_start')
			$col.append($('<div class="weather_later_day_label"></div>').text(item.label_day))
		} else {
			$col.append($('<div class="weather_later_day_label"></div>').html('&nbsp;'))
		}
		var hl = (bl.hour_start < 10 ? '0' : '') + bl.hour_start
		$col.append($('<div class="weather_later_hour_label"></div>').text(hl))
		$col.append(
			$('<div class="weather_hour_sky weather_sky" aria-hidden="true"></div>')
				.html(weather_sky_html(bl.sky || 'cloudy', bl.sky_glyph, bl.sky_overlay, bl.sky_overlay_glyph, bl.sky_stack))
		)
		$col.append(weather_precip_el(bl))
		$col.append(weather_wind_el(bl))
		var ms = (bl.wind_ms != null) ? String(bl.wind_ms) : ''
		$col.append($('<div class="weather_hour_ms"></div>').text(ms))
		$row.append($col)
	}

}

/**
 * Precip: rain drops, snow flakes, or fog dots — level 0 / 1 / 3.
 */
function weather_precip_el(sl){

	var kind = sl.precip_kind || 'none'
	var level = parseInt(sl.precip_level, 10) || 0
	var $p = $('<div class="weather_hour_precip"></div>')

	if (kind === 'none' || level <= 0){
		$p.html('&nbsp;')
		return $p
	}

	if (kind === 'fog'){
		$p.addClass('weather_precip_fog')
		$p.text(level >= 3 ? '···' : '·')
		return $p
	}

	if (kind === 'snow'){
		$p.addClass('weather_precip_snow')
		$p.text(level >= 3 ? '❄❄❄' : '❄')
		return $p
	}

	// rain drops
	$p.text(level >= 3 ? '💧💧💧' : '💧')
	return $p

}

function weather_wind_el(sl){

	var $w = $('<div class="weather_hour_wind"></div>')
	if (sl.missing || sl.wind_ms == null){
		return $w
	}

	// Arrow points in direction wind blows TOWARD (from + 180), CSS triangle points up = north
	var dir = parseFloat(sl.wind_dir) || 0
	var rot = dir + 180
	var $arrow = $('<div class="weather_wind_arrow"></div>')
	$arrow.css('transform', 'rotate(' + rot + 'deg)')
	$w.append($arrow)

	var flags = parseInt(sl.wind_flags, 10) || 0
	var $flags = $('<div class="weather_wind_flags"></div>')
	// half-flags: each unit ≈ 5 mph; full flag = 2 halves
	var full = Math.floor(flags / 2)
	var half = flags % 2
	var f
	for (f = 0; f < full && f < 5; f++){
		$flags.append($('<span class="weather_wind_flag"></span>'))
	}
	if (half){
		$flags.append($('<span class="weather_wind_flag weather_wind_flag_half"></span>'))
	}
	$w.append($flags)
	return $w

}

function weather_draw_temp_canvas(canvas, slots, is_later){

	if (!canvas){
		return
	}

	var wrap = canvas.parentNode
	var css_w = wrap ? wrap.clientWidth : 800
	var css_h = Math.max(80, Math.round((wrap ? wrap.clientHeight : 400) * 0.38))
	if (css_w < 10 || css_h < 10){
		return
	}

	var dpr = window.devicePixelRatio || 1
	canvas.width = Math.round(css_w * dpr)
	canvas.height = Math.round(css_h * dpr)
	canvas.style.width = css_w + 'px'
	canvas.style.height = css_h + 'px'

	var ctx = canvas.getContext('2d')
	ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
	ctx.clearRect(0, 0, css_w, css_h)

	var rem = (typeof _cms_rem === 'number' && _cms_rem > 0)
		? _cms_rem
		: parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 14

	var pad = { top: 1.2 * rem, right: 0.8 * rem, bottom: 1.4 * rem, left: 2.4 * rem }
	var plot_w = css_w - pad.left - pad.right
	var plot_h = css_h - pad.top - pad.bottom

	var temps = []
	var i
	for (i = 0; i < slots.length; i++){
		if (slots[i] && slots[i].temp_c != null && isFinite(slots[i].temp_c)){
			temps.push(+slots[i].temp_c)
		}
	}
	if (!temps.length){
		ctx.fillStyle = WEATHER_LABEL
		ctx.font = (1.2 * rem) + 'px sans-serif'
		ctx.textAlign = 'center'
		ctx.fillText('No temperature data', css_w / 2, css_h / 2)
		return
	}

	var tmin = Math.floor(Math.min.apply(null, temps) - 1)
	var tmax = Math.ceil(Math.max.apply(null, temps) + 1)
	if (tmax <= tmin){
		tmax = tmin + 2
	}

	function x_at(idx, n){
		if (n <= 1){
			return pad.left + plot_w / 2
		}
		return pad.left + (idx / (n - 1)) * plot_w
	}

	function y_at(v){
		return pad.top + plot_h * (1 - (v - tmin) / (tmax - tmin))
	}

	// Grid
	ctx.font = (1.05 * rem) + 'px sans-serif'
	ctx.textAlign = 'right'
	ctx.textBaseline = 'middle'
	var step = tmax - tmin > 12 ? 4 : 2
	var gy
	for (gy = tmin; gy <= tmax; gy += step){
		var yy = y_at(gy)
		ctx.strokeStyle = gy === 0 ? WEATHER_AXIS : WEATHER_GRID
		ctx.lineWidth = gy === 0 ? 1.25 : 1
		ctx.setLineDash(gy === 0 ? [] : [4, 4])
		ctx.beginPath()
		ctx.moveTo(pad.left, yy)
		ctx.lineTo(pad.left + plot_w, yy)
		ctx.stroke()
		ctx.setLineDash([])
		ctx.fillStyle = WEATHER_LABEL
		ctx.fillText(String(gy) + '°', pad.left - 0.35 * rem, yy)
	}

	// Line
	var n = slots.length
	ctx.beginPath()
	ctx.strokeStyle = WEATHER_LINE
	ctx.lineWidth = 2.25
	ctx.lineJoin = 'round'
	ctx.lineCap = 'round'
	var started = false
	for (i = 0; i < n; i++){
		var v = slots[i] && slots[i].temp_c
		if (v == null || !isFinite(v)){
			started = false
			continue
		}
		var x = x_at(i, n)
		var y = y_at(+v)
		if (!started){
			ctx.moveTo(x, y)
			started = true
		} else {
			ctx.lineTo(x, y)
		}
	}
	ctx.stroke()

}

/**
 * Later week: high/low temp lines across days (daily points).
 */
function weather_draw_later_canvas(canvas, later_days){

	if (!canvas){
		return
	}

	var wrap = canvas.parentNode
	var css_w = wrap ? wrap.clientWidth : 800
	var css_h = Math.max(80, Math.round((wrap ? wrap.clientHeight : 400) * 0.38))
	if (css_w < 10 || css_h < 10){
		return
	}

	var dpr = window.devicePixelRatio || 1
	canvas.width = Math.round(css_w * dpr)
	canvas.height = Math.round(css_h * dpr)
	canvas.style.width = css_w + 'px'
	canvas.style.height = css_h + 'px'

	var ctx = canvas.getContext('2d')
	ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
	ctx.clearRect(0, 0, css_w, css_h)

	var rem = (typeof _cms_rem === 'number' && _cms_rem > 0)
		? _cms_rem
		: parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 14

	var pad = { top: 1.2 * rem, right: 0.8 * rem, bottom: 1.6 * rem, left: 2.4 * rem }
	var plot_w = css_w - pad.left - pad.right
	var plot_h = css_h - pad.top - pad.bottom

	var days = later_days || []
	if (!days.length){
		ctx.fillStyle = WEATHER_LABEL
		ctx.font = (1.2 * rem) + 'px sans-serif'
		ctx.textAlign = 'center'
		ctx.fillText('No further forecast days', css_w / 2, css_h / 2)
		return
	}

	// Prefer 6h series for a denser graph when blocks exist
	var points_max = []
	var points_min = []
	var seps = [] // x positions of day starts
	var di, bi
	for (di = 0; di < days.length; di++){
		var day = days[di]
		var blocks = day.blocks_6h || []
		if (!blocks.length){
			points_max.push({ day_start: true, label: day.label_dow, v: day.temp_max })
			points_min.push({ day_start: true, label: day.label_dow, v: day.temp_min })
			continue
		}
		for (bi = 0; bi < blocks.length; bi++){
			var bl = blocks[bi]
			points_max.push({
				day_start: bi === 0,
				label: bi === 0 ? day.label_dow : '',
				v: bl.temp_max != null ? bl.temp_max : bl.temp_c
			})
			points_min.push({
				day_start: bi === 0,
				label: '',
				v: bl.temp_min != null ? bl.temp_min : bl.temp_c
			})
		}
	}

	var vals = []
	var i
	for (i = 0; i < points_max.length; i++){
		if (points_max[i].v != null) vals.push(+points_max[i].v)
		if (points_min[i] && points_min[i].v != null) vals.push(+points_min[i].v)
	}
	if (!vals.length){
		return
	}

	var tmin = Math.floor(Math.min.apply(null, vals) - 1)
	var tmax = Math.ceil(Math.max.apply(null, vals) + 1)
	if (tmax <= tmin){
		tmax = tmin + 2
	}

	var n = points_max.length

	function x_at(idx){
		if (n <= 1){
			return pad.left + plot_w / 2
		}
		return pad.left + (idx / (n - 1)) * plot_w
	}

	function y_at(v){
		return pad.top + plot_h * (1 - (v - tmin) / (tmax - tmin))
	}

	// Day separators (stronger)
	ctx.strokeStyle = WEATHER_DAY_SEP
	ctx.lineWidth = 2
	for (i = 0; i < n; i++){
		if (points_max[i].day_start && i > 0){
			var sx = x_at(i)
			ctx.beginPath()
			ctx.moveTo(sx, pad.top)
			ctx.lineTo(sx, pad.top + plot_h)
			ctx.stroke()
		}
	}

	// Horizontal grid
	ctx.font = (1.05 * rem) + 'px sans-serif'
	ctx.textAlign = 'right'
	ctx.textBaseline = 'middle'
	var step = tmax - tmin > 12 ? 4 : 2
	for (var gy = tmin; gy <= tmax; gy += step){
		var yy = y_at(gy)
		ctx.strokeStyle = WEATHER_GRID
		ctx.lineWidth = 1
		ctx.setLineDash([4, 4])
		ctx.beginPath()
		ctx.moveTo(pad.left, yy)
		ctx.lineTo(pad.left + plot_w, yy)
		ctx.stroke()
		ctx.setLineDash([])
		ctx.fillStyle = WEATHER_LABEL
		ctx.fillText(String(gy) + '°', pad.left - 0.35 * rem, yy)
	}

	function draw_line(points, colour, width){
		ctx.beginPath()
		ctx.strokeStyle = colour
		ctx.lineWidth = width
		ctx.lineJoin = 'round'
		var started = false
		for (var j = 0; j < points.length; j++){
			if (points[j].v == null || !isFinite(points[j].v)){
				started = false
				continue
			}
			var x = x_at(j)
			var y = y_at(+points[j].v)
			if (!started){
				ctx.moveTo(x, y)
				started = true
			} else {
				ctx.lineTo(x, y)
			}
		}
		ctx.stroke()
	}

	draw_line(points_min, WEATHER_LINE_MIN, 1.75)
	draw_line(points_max, WEATHER_LINE, 2.25)

	// Day labels under plot
	ctx.textAlign = 'center'
	ctx.textBaseline = 'top'
	ctx.fillStyle = WEATHER_LINE
	ctx.font = (1.0 * rem) + 'px sans-serif'
	for (i = 0; i < n; i++){
		if (points_max[i].day_start && points_max[i].label){
			ctx.fillText(points_max[i].label, x_at(i), pad.top + plot_h + 0.25 * rem)
		}
	}

}

$(function(){
	weather_init()
	// SPA: re-init after main position swap (single page mode)
	if (!window.weather_position_hook_ok){
		window.weather_position_hook_ok = true
		if (!window.cms_position_link_after){
			window.cms_position_link_after = []
		}
		window.cms_position_link_after.push(function(){
			weather_init()
		})
	}
})
