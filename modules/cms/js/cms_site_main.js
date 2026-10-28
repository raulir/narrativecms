'use strict'

function _cms_get_base() {
	return (typeof _cms_base !== 'undefined' ? _cms_base : '/')
}

function cms_ajax_app_session(params){

	params = params || {}

	if (!window._cms_inapp){
		return params
	}

	var saved = {}

	try {
		saved = JSON.parse(localStorage.getItem('app_session') || '') || {}
	} catch (e) {
		saved = {}
	}

	if (saved.session_id && !params.session_id){
		params.session_id = saved.session_id
	}

	return params

}

function get_ajax(name, params) {

	var data = Object.assign({
		'no_html': '1'
	}, cms_ajax_app_session(params || {}))
	var on_success = data.success
	var on_error = data.error
	delete data.success
	delete data.error

	return cms_ajax_panel(name, data).then(function(result) {
		if (typeof on_success === 'function') {
			on_success(result)
		}
		return result
	}, function(err) {
		if (typeof on_error === 'function') {
			on_error(err)
		}
		return Promise.reject(err)
	})

}

function get_ajax_panel(name, args, callback) {

	var promise = cms_ajax_panel(name, cms_ajax_app_session(args || {}))

	if (typeof callback !== 'function') {
		return promise
	}

	var chained = promise.then(function(result) {
		callback(result)
		return result
	})
	chained.catch(function(){})
	return chained

}

function cms_access_denied_popup(error) {
	if ($('.cms_access_denied_container').length) {
		return
	}

	error = error || {}

	get_ajax_panel('cms/cms_access_denied', {
		login_url: error.login_url || (typeof _cms_login_url !== 'undefined' ? _cms_login_url : _cms_get_base()),
		login_text: error.login_text || (typeof _cms_login_text !== 'undefined' ? _cms_login_text : 'Login'),
		text: 'System error: access denied',
	}, function(data) {
		$('body').append(data.result._html)
	}).catch(function() {})
}

function cms_apply_panel_css(data, finish) {

	var panel_css = data && data._panel_css

	if (!panel_css && data && data.result) {
		panel_css = data.result._panel_css
	}

	if (panel_css && panel_css.length) {
		var force_css = !!(data._panel_css_force || (data.result && data.result._panel_css_force))
		cms_load_css(panel_css, force_css).then(finish)
	} else {
		finish()
	}

}

function cms_panel_cache_flag(value) {

	return !(value === undefined || value === null || value === false || value === 0 || value === '0' || value === '')

}

function cms_panel_cache_on() {

	if (typeof admin_logged_in !== 'undefined' && admin_logged_in) {
		return false
	}

	try {
		return typeof localStorage !== 'undefined' && localStorage !== null
	} catch (e) {
		return false
	}

}

function cms_panel_cache_hash(text) {

	var seeds = [5381, 52711, 2166136261, 16777619]
	var parts = []
	var s
	var hash
	var i

	for (s = 0; s < seeds.length; s++) {
		hash = seeds[s]
		for (i = 0; i < text.length; i++) {
			hash = ((hash << 5) + hash) ^ text.charCodeAt(i)
			hash = hash | 0
		}
		parts.push((hash >>> 0).toString(16))
	}

	return parts.join('')

}

function cms_ajax_sort(value) {

	var sorted
	var keys
	var i

	if (!value || typeof value !== 'object') {
		return value
	}

	if (Object.prototype.toString.call(value) !== '[object Object]') {
		return value
	}

	sorted = {}
	keys = Object.keys(value).sort()

	for (i = 0; i < keys.length; i++) {
		sorted[keys[i]] = cms_ajax_sort(value[keys[i]])
	}

	return sorted

}

function cms_panel_cache_storable(data) {

	return !!(data && data.result && !data.error)

}

function cms_panel_cache_user(){

	if (typeof _cms_user_id !== 'undefined' && _cms_user_id){
		return String(_cms_user_id)
	}

	return '0'

}

function cms_panel_cache_key(name, params) {

	return 'cms_panel_' + cms_panel_cache_hash(_cms_get_base() + '\n' + cms_panel_cache_user() + '\n' + name + '\n' + JSON.stringify(params))

}

function cms_panel_cache_read(key) {

	if (!cms_panel_cache_on()) {
		return null
	}

	try {
		var raw = localStorage.getItem(key)
		if (!raw) {
			return null
		}
		var stored = JSON.parse(raw)
		if (!stored || typeof stored !== 'object' || !stored.data) {
			return null
		}
		return stored
	} catch (e) {
		return null
	}

}

function cms_panel_cache_write(key, data) {

	if (!cms_panel_cache_on()) {
		return
	}

	try {
		localStorage.setItem(key, JSON.stringify({
			t: Math.floor(Date.now() / 1000),
			data: data
		}))
	} catch (e) {}

}

function cms_ajax_panel(name, args) {
	var params = cms_ajax_sort(Object.assign({}, args))

	if (window._cms_inapp){
		params._cms_inapp = 1
	}

	var cache_seconds = parseInt(params._cache, 10)
	if (isNaN(cache_seconds) || cache_seconds < 0) {
		cache_seconds = 0
	}
	var cache_offline = cms_panel_cache_flag(params._cache_offline)
	var cache_reload = cms_panel_cache_flag(params._cache_reload)
	delete params._cache
	delete params._cache_offline
	delete params._cache_reload

	var ajax_timeout = params.timeout
	if (params._ajax_timeout !== undefined && params._ajax_timeout !== null) {
		ajax_timeout = params._ajax_timeout
	}
	delete params._ajax_timeout
	delete params._ajax_error
	// Never POST callback/timeout fields
	delete params.success
	delete params.error
	delete params.timeout
	delete params.complete

	params.panel_id = name
	params = cms_ajax_sort(params)

	var cache_key = cms_panel_cache_key(name, params)
	var stored = cms_panel_cache_read(cache_key)
	var stored_age = stored ? (Math.floor(Date.now() / 1000) - (parseInt(stored.t, 10) || 0)) : 0
	var cache_fresh = !!(stored && cache_seconds > 0 && stored_age < cache_seconds)

	return new Promise((resolve, reject) => {

		var fail = function(err) {
			reject(err)
		}

		var deliver = function(returned_data) {

			if (returned_data && returned_data.error && returned_data.error.message === 'access_denied') {
				if (name !== 'cms/cms_access_denied') {
					cms_access_denied_popup(returned_data.error)
				}
				fail({
					xhr: null,
					status: 'error',
					error: returned_data.error,
					message: 'access_denied'
				})
				return
			}

			if (returned_data && typeof returned_data.result == 'object') {
				var script_srcs = []
				var prepare_field = function(value){
					var prepared = cms_inapp_prepare_html(value)
					script_srcs = script_srcs.concat(prepared.srcs || [])
					return prepared.html
				}
				if (typeof returned_data.result._html == 'string') {
					returned_data.result._html = prepare_field(returned_data.result._html)
				}
				if (typeof returned_data.result.html == 'string') {
					returned_data.result.html = prepare_field(returned_data.result.html)
				} else if (typeof returned_data.result._html == 'string') {
					returned_data.result.html = returned_data.result._html
				}
				$.each(returned_data.result, (key, value) => {
					if (key === '_html' || key === 'html') {
						return
					}
					if (typeof value == 'string') {
						value = prepare_field(value)
						returned_data.result[key] = value
						$('.__' + name.replace('/', '__') + '__' + key).html(value)
					} else if (typeof value == 'number' || typeof value == 'bigint') {
						$('.__' + name.replace('/', '__') + '__' + key).html(value)
					}
				})
				var scripts_done = (typeof cms_inapp_load_scripts === 'function')
					? cms_inapp_load_scripts(script_srcs)
					: Promise.resolve()
				scripts_done.then(function(){
					if (typeof cms_position_link_init === 'function') {
						cms_position_link_init()
					}
				})
			}

			var finish = function() {
				resolve(returned_data)
			}

			cms_apply_panel_css(returned_data, finish)

		}

		if (typeof window.music_offline_take === 'function' && window.music_offline_take(name, params, stored, deliver, fail)){
			return
		}

		// Reload wins over offline: jQuery POST first. A stored copy is only the failure path.
		if (!cache_reload && (cache_fresh || (cache_offline && stored))) {
			deliver(stored.data)
			return
		}

		if (cache_offline && !cache_reload && !stored && typeof navigator !== 'undefined' && navigator.onLine === false) {
			if (typeof music_offline_notice === 'function'){
				music_offline_notice()
			}
			fail({
				xhr: null,
				status: 'offline',
				error: 'offline',
				message: 'Request failed: offline'
			})
			return
		}

		var ajax_opts = {
			type: 'POST',
			url: _cms_get_base() + 'ajax_api/get_panel/',
			data: params,
			dataType: 'json',
			success: function(returned_data) {

				if (cache_seconds > 0 && cms_panel_cache_storable(returned_data)) {
					cms_panel_cache_write(cache_key, returned_data)
				}

				deliver(returned_data)

			},
			error: function(xhr, status, err) {

				if (stored) {
					deliver(stored.data)
					return
				}

				fail({
					xhr: xhr,
					status: status,
					error: err,
					message: status === 'timeout'
						? 'Request timed out'
						: ('Request failed' + (err ? (': ' + err) : ''))
				})
			}
		}

		if (ajax_timeout !== undefined && ajax_timeout !== null && ajax_timeout !== '') {
			ajax_opts.timeout = parseInt(ajax_timeout, 10) || 0
		}

		$.ajax(ajax_opts)
	})
}

function _panels_popup_get_overlay($popup) {

	var $overlay = $popup.next('.popup_overlay, .cms_popup_overlay')
	if (!$overlay.length) {
		$overlay = $popup.prev('.popup_overlay, .cms_popup_overlay')
	}

	return $overlay

}

function _panels_popup_store_fragment($popup, $appended){

	if ($popup && $popup.length && $appended && $appended.length){
		$popup.data('cms_popup_appended', $appended)
	}

}

function _panels_popup_remove_fragment($popup){

	if (!$popup || !$popup.length || !$popup.closest('body').length){
		return
	}

	var $overlay = _panels_popup_get_overlay($popup)
	var $appended = $popup.data('cms_popup_appended')

	$popup.removeData('cms_popup_dismiss')
	$popup.removeData('cms_popup_appended')
	$(document).off('keyup.cms_popup_dismiss')

	$popup.css({'opacity': '0', 'pointer-events': 'none'})
	if ($overlay.length){
		$overlay.css({'opacity': '0', 'pointer-events': 'none'})
	}

	setTimeout(function(){

		if ($appended && $appended.length){
			$appended.remove()
		} else if ($popup.closest('body').length){
			$popup.remove()
		}

		$overlay.remove()
		_panels_popup_bind_escape()

	}, 300)

}

function _panels_popup_top_dismiss() {

	var $top_popup = null
	var dismiss = null

	$('.cms_popup_container, .popup_container').each(function(){
		var current_dismiss = $(this).data('cms_popup_dismiss')
		if (current_dismiss && $(this).closest('body').length) {
			$top_popup = $(this)
			dismiss = current_dismiss
		}
	})

	return {
		'$popup': $top_popup,
		'dismiss': dismiss,
	}

}

function _panels_popup_bind_escape() {

	$(document).off('keyup.cms_popup_dismiss')

	var top = _panels_popup_top_dismiss()
	if (!top.dismiss || !top.$popup || !top.$popup.length) {
		return
	}

	$(document).on('keyup.cms_popup_dismiss', function(e) {

		if (e.which !== 27) {
			return
		}

		var current = _panels_popup_top_dismiss()
		if (!current.dismiss || !current.$popup || !current.$popup.length) {
			return
		}

		e.preventDefault()
		e.stopImmediatePropagation()
		current.dismiss()

	})

}

function panels_display_popup(html, params) {

	var $appended = $($.parseHTML(html, document, true))
	$('body').append($appended)

	var $popup = $appended.filter('.cms_popup_container, .popup_container')
		.add($appended.find('.cms_popup_container, .popup_container')).first()

	if (!$popup.length) {
		return
	}

	_panels_popup_store_fragment($popup, $appended)

	params = $.extend({
		'yes': function(after) {
			after()
		},
		'select': function(after) {
			after()
		},
		'cancel': function(after) {
			after()
		},
		'pre_close': function(after) {
			after()
		},
		'clean_up': function() {

		}
	}, params)

	var clean_up = function() {
		_panels_popup_remove_fragment($popup)
	}

	var dismiss_popup = function() {

		params.pre_close(function() {
			params.cancel(function() {
				clean_up()
				params.clean_up()
			})
		})

	}

	$popup.data('cms_popup_dismiss', dismiss_popup)

	if ($popup.hasClass('cms_popup_container')) {
		$popup.css({'display': 'table', 'opacity': '1'})
	} else if ($popup.hasClass('popup_container')) {
		$popup.css({'display': 'block', 'opacity': '1'})
	}

	if (typeof cms_popup_yes_no_init === 'function' && $popup.find('.cms_popup_body').length) {
		cms_popup_yes_no_init($popup)
	}

	$popup.find('.popup_yes').off('click.r').on('click.r', function() {
		params.pre_close(function() {
			var finished = false
			var finish = function() {
				if (finished) {
					return
				}
				finished = true
				clean_up()
				params.clean_up()
			}
			params.yes(finish)
			finish()
		})
	})

	$popup.find('.popup_cancel').off('click.r').on('click.r', dismiss_popup)

	$popup.find('.popup_no, .popup_close').off('click.r').on('click.r', dismiss_popup)

	$popup.find('.popup_select').off('click.r').on('click.r', function() {
		params.pre_close(function() {
			params.select(function() {
				clean_up()
				params.clean_up()
			})
		})
	})

	_panels_popup_bind_escape()

}

function cms_inapp_drop_v(query){

	if (!query){
		return ''
	}

	var parts = query.replace(/^\?/, '').split('&').filter(function(part){
		return part && part.indexOf('v=') !== 0
	})

	return parts.length ? '?' + parts.join('&') : ''

}

function cms_inapp_asset_url(filename){

	if (!window._cms_inapp || typeof _cms_base !== 'string' || _cms_base.indexOf('http') !== 0 || typeof filename !== 'string'){
		return filename
	}

	var base = _cms_base.replace(/\/+$/, '')
	var text = filename

	if (text.indexOf(base) === 0){
		text = text.slice(base.length)
	}

	var hash = ''
	var query = ''
	var hash_at = text.indexOf('#')

	if (hash_at !== -1){
		hash = text.slice(hash_at)
		text = text.slice(0, hash_at)
	}

	var query_at = text.indexOf('?')

	if (query_at !== -1){
		query = text.slice(query_at)
		text = text.slice(0, query_at)
	}

	var path = text.charAt(0) === '/' ? text : '/' + text.replace(/^\/+/, '')
	var relative = path.replace(/^\//, '')

	if (relative.indexOf('cache/') !== 0 && relative.indexOf('modules/') !== 0 && relative.indexOf('img/') !== 0){
		return filename
	}

	return path + cms_inapp_drop_v(query) + hash

}

function cms_inapp_html(html){

	if (!window._cms_inapp || typeof html !== 'string' || typeof _cms_base !== 'string' || _cms_base.indexOf('http') !== 0){
		return html
	}

	return cms_inapp_rewrite_text(html, _cms_base)

}

function cms_inapp_script_path(src){

	var value = String(src || '')

	try {
		if (value.indexOf('http') === 0){
			value = new URL(value).pathname
		}
	} catch (e) {}

	var cut = value.indexOf('?')
	if (cut !== -1){
		value = value.slice(0, cut)
	}

	return value

}

function cms_inapp_script_loaded(src){

	var path = cms_inapp_script_path(src)
	var nodes = document.getElementsByTagName('script')

	for (var i = 0; i < nodes.length; i++){
		if (cms_inapp_script_path(nodes[i].getAttribute('src')) === path){
			return true
		}
	}

	return false

}

function cms_inapp_prepare_html(html){

	html = cms_inapp_html(html)

	if (!window._cms_inapp || typeof html !== 'string'){
		return {html: html, srcs: []}
	}

	var srcs = []
	var cleaned = html.replace(/<script\b([^>]*)>[\s\S]*?<\/script>/gi, function(tag, attrs){

		var match = String(attrs || '').match(/\bsrc\s*=\s*(['"])([^'"]+)\1/i)
		if (!match){
			return tag
		}

		srcs.push(match[2])
		return ''

	})

	return {html: cleaned, srcs: srcs}

}

function cms_inapp_load_scripts(srcs){

	var pending = []

	;(srcs || []).forEach(function(src){
		var url = cms_inapp_asset_url(src)
		if (!url || cms_inapp_script_loaded(url)){
			return
		}
		pending.push(url)
	})

	var chain = Promise.resolve()

	pending.forEach(function(url){
		chain = chain.then(function(){
			return new Promise(function(resolve){
				var script = document.createElement('script')
				script.src = url
				script.async = false
				script.onload = function(){ resolve() }
				script.onerror = function(){ resolve() }
				document.body.appendChild(script)
			})
		})
	})

	return chain

}

function cms_inapp_rewrite_text(html, base){

	function esc(text){
		return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
	}

	base = String(base || '').replace(/\/+$/, '')
	html = String(html).replace(new RegExp(esc(base) + '(/(?:cache|modules|img)/)', 'g'), '$1')

	var slashed = base.replace(/\//g, '\\/')
	html = html.replace(new RegExp(esc(slashed) + '(\\\\/(?:cache|modules|img)\\\\/)', 'g'), '$1')

	return html

}

function _cms_load_css(filename) {
	return new Promise((resolve) => {

		let link = document.createElement('link')

		link.type = 'text/css'
		link.rel = 'stylesheet'
		link.addEventListener('load', resolve)
		link.href = cms_inapp_asset_url(filename)

		document.head.appendChild(link)

	})
}

function cms_load_css(filenames, force_download, class_to_remove) {

	return new Promise((resolve) => {

		var load_total = 0
		var load_finished = 0

		$(filenames).each(function(key, filename) {

			if (filename.indexOf('?') !== -1) {
				var clean_filename = filename.substr(0, filename.indexOf('?'))
			} else {
				var clean_filename = filename
			}

			var found = false

			$('link[type="text/css"]').each(function() {
				if (this.href.indexOf(clean_filename) !== -1) {
					found = true
				}
			})

			if (!found) {

				if (force_download) {
					filename = clean_filename + '?v=' + Math.round(Math.random() * 10000000)
				}

				load_total += 1
				_cms_load_css(filename).then(() => {
					load_finished += 1
					setTimeout(() => {
						if (load_finished == load_total) {
							resolve()
						}
					}, 100)
				})

			}

		})

		if (load_total == 0) {
			resolve()
		} else {
			setTimeout(() => {
				resolve()
			}, 5000)
		}

	})

}

function get_api(name, params) {

	var ext_params = $.extend({'success': function() {}, 'error': function() {} }, params)
	var action_on_success = ext_params.success
	var action_on_error = ext_params.error
	delete ext_params.success
	delete ext_params.error

	$.ajax({
		type: 'POST',
		url: _cms_get_base() + name,
		data: ext_params,
		dataType: 'json',
		success: function(returned_data) {
			action_on_success(returned_data)
		},
		error: function(xhr, status, err) {
			action_on_error(xhr, status, err)
		}
	})

}

function cms_hover_init($root) {

	var $scope = $root ? $root.find('.cms_hover_button') : $('.cms_hover_button');

	$scope.not('.cms_hover_ok').each(function() {

		var $this = $(this)

		if (!$this.data('normal_image')) {
			$this.data('normal_image', $this.css('background-image'))
		}

		$this.addClass('cms_hover_ok')

		$this.on('mouseenter.cms', function() {
			if (!$this.hasClass('cms_hover_disabled')) {
				$this.addClass($this.data('hover_class'))
				setTimeout(function() {
					$this.css({'background-image':$this.data('hover_image')})
				}, 150)
			}
		})

		$this.on('mouseleave.cms', function() {
			if (!$this.hasClass('cms_hover_disabled')) {
				$this.removeClass($this.data('hover_class'))
				setTimeout(function() {
					$this.css({'background-image':$this.data('normal_image')})
				}, 150)
			}
		})

	})

}

if (!window.console) {
	window.console = {}
	window.console.log = function() {}
	window.console.dir = function() {}
}

Object.keys = Object.keys || function(o) {
	var keysArray = []
	for (var name in o) {
		if (o.hasOwnProperty(name)) {
			keysArray.push(name)
		}
	}
	return keysArray
}

if (typeof String.prototype.endsWith !== 'function') {
	String.prototype.endsWith = function(suffix) {
		return this.indexOf(suffix, this.length - suffix.length) !== -1
	}
}

if (!Array.prototype.forEach) {
	Array.prototype.forEach = function(callback) {
		var T, k
		if (this == null) {
			throw new TypeError('this is null or not defined')
		}
		var O = Object(this)
		var len = O.length >>> 0
		if (typeof callback !== 'function') {
			throw new TypeError(callback + ' is not a function')
		}
		if (arguments.length > 1) {
			T = arguments[1]
		}
		k = 0
		while (k < len) {
			if (k in O) {
				callback.call(T, O[k], k, O)
			}
			k++
		}
	}
}

var cms_disable_zoom = function() {
	if (!(/iPad|iPhone|iPod/.test(navigator.userAgent))) {
		return
	}
	$(document.head).append('<style>*{cursor:pointer;-webkit-tap-highlight-color:rgba(0,0,0,0)}</style>')
	$(window).on('gesturestart touchmove', function(evt) {
		if (evt.originalEvent.scale !== 1) {
			evt.originalEvent.preventDefault()
			document.body.style.transform = 'scale(1)'
		}
	})
}

$(document).ready(function() {

	cms_hover_init()
	cms_disable_zoom()

})