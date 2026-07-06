'use strict'

function _cms_get_base() {
	return (typeof _cms_base !== 'undefined' ? _cms_base : '/')
}

function get_ajax(name, params) {
	return new Promise((resolve) => {
		var ext_params = Object.assign({
			'no_html': '1',
			'success': data => resolve(data)
		}, params)

		var action_on_success = ext_params.success
		delete ext_params.success

		get_ajax_panel(name, ext_params, action_on_success)
	})
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
	})
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

function get_ajax_panel(name, args, action_on_success) {
	var params = Object.assign({}, args)
	delete params._ajax_cache

	params.panel_id = name

	return new Promise((resolve) => {
		$.ajax({
			type: 'POST',
			url: _cms_get_base() + 'ajax_api/get_panel/',
			data: params,
			dataType: 'json',
			success: function(returned_data) {

				if (returned_data.error && returned_data.error.message === 'access_denied') {
					if (name !== 'cms/cms_access_denied') {
						cms_access_denied_popup(returned_data.error)
					}
					return
				}

				if ((typeof returned_data.result != 'undefined') && (typeof returned_data.result._html != 'undefined') && !returned_data.result.html) {
					returned_data.result.html = returned_data.result._html
				}

				if (typeof returned_data.result == 'object') {
					$.each(returned_data.result, (key, value) => {
						if (key === '_html' || key === 'html') {
							return
						}
						if (typeof value == 'string' || typeof value == 'number' || typeof value == 'bigint') {
							$('.__' + name.replace('/', '__') + '__' + key).html(value)
						}
					})
				}

				var finish = function() {
					if (action_on_success) {
						action_on_success(returned_data)
					} else {
						resolve(returned_data)
					}
				}

				cms_apply_panel_css(returned_data, finish)

			}
		})
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

function _cms_load_css(filename) {
	return new Promise((resolve) => {

		let link = document.createElement('link')

		link.type = 'text/css'
		link.rel = 'stylesheet'
		link.addEventListener('load', resolve)
		link.href = filename

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

	var ext_params = $.extend({'success': function() {} }, params)
	var action_on_success = ext_params.success
	delete ext_params.success

	$.ajax({
		type: 'POST',
		url: _cms_get_base() + name,
		data: ext_params,
		dataType: 'json',
		success: function(returned_data) {
			action_on_success(returned_data)
		}
	})

}

function change_url(new_url) {
	if (history && history.pushState) {

		if (!window.location.href.endsWith(new_url) || new_url == '/') {
			history.pushState({}, '', new_url)
		}

	}
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