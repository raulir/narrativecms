var cms_position_last_url = window.location.href
var cms_position_nav_busy = false

function get_ajax_positions(url, params, action_on_success) {

	params._url = url
	params._ajax = 1

	$.ajax({
		type: 'POST',
		url: params._url,
		data: params,
		dataType: 'json',
		success: function(returned_data) {

			if (returned_data.redirect) {
				get_ajax_positions(returned_data.redirect, params, action_on_success)
				return
			}

			if (returned_data.error && returned_data.error.message === 'access_denied') {
				cms_access_denied_popup(returned_data.error)
				return
			}

			returned_data._final_url = params._url
			action_on_success(returned_data)

		},
		error: function() {
			$('.cms_position_main').css({'opacity':''})
			cms_position_nav_busy = false
		}
	})

}

function cms_position_collect() {

	var positions = {}
	$('.cms_position').each(function(){
		var $this = $(this)
		positions[$this.data('position')] = $this.data('cms_page_id')
	})
	return positions

}

function cms_position_sync_url_tracking() {
	cms_position_last_url = window.location.href
}

function cms_position_push_url(new_url) {

	if (!(history && history.pushState)) {
		return
	}

	if (!window.location.href.endsWith(new_url) || new_url == '/') {
		history.pushState({cms_position: 1}, '', new_url)
	}

	cms_position_sync_url_tracking()

}

function cms_position_apply_result(result, opts) {

	opts = opts || {}

	return new Promise(function(resolve) {

		var apply_positions = function() {

			var needs_cache_hydrate = false

			$.each(result.positions, function(i, posdata){
				$('.cms_position_' + i).html(posdata._html).data('cms_page_id', posdata.cms_page_id)
				if (posdata.has_deferred) {
					needs_cache_hydrate = true
				}
			})

			if (needs_cache_hydrate && typeof cms_cache_hydrate === 'function') {
				cms_cache_hydrate()
			}

			var final_url = result._final_url || opts.url || window.location.href

			if (opts.history === 'push') {
				cms_position_push_url(final_url)
			} else {
				cms_position_sync_url_tracking()
			}

			if (result.title) {
				document.title = result.title
			}

			if (typeof gtag != 'undefined') {
				var $a = $('<a href="' + final_url + '"></a>')
				var page = $a[0].pathname + $a[0].hash
				gtag('event', 'page_view', {
					page_title: result.title,
					page_path: page
				})
			}

			cms_position_link_init()

			if (window.cms_position_link_after && window.cms_position_link_after.length) {
				window.cms_position_link_after.forEach(function(element) {
					element()
				})
			}

			resolve()

		}

		if (typeof cms_apply_panel_css === 'function') {
			cms_apply_panel_css(result, apply_positions)
		} else {
			apply_positions()
		}

	})

}

/**
 * SPA navigate to url by swapping cms positions.
 * opts.history: 'push' (default, click) | 'none' (popstate — browser already changed URL)
 * opts.animate: dim main while loading (default true)
 */
function cms_position_goto(url, opts) {

	opts = opts || {}
	var history_mode = opts.history || 'push'
	var animate = opts.animate !== false

	if (cms_position_nav_busy) {
		return Promise.resolve()
	}

	cms_position_nav_busy = true

	var before = Promise.resolve()
	if (animate) {
		before = new Promise(function(resolve) {
			$('.cms_position_main').css({'opacity':'0.5'})
			setTimeout(resolve, 300)
		})
	}

	var download = new Promise(function(resolve) {
		get_ajax_positions(url, {'cms_positions': cms_position_collect()}, function(result) {
			resolve(result)
		})
	})

	return Promise.all([before, download]).then(function(parts) {
		var result = parts[1]
		return cms_position_apply_result(result, {
			history: history_mode,
			url: url
		})
	}).then(function() {
		if (animate) {
			return new Promise(function(resolve) {
				setTimeout(function() {
					$('.cms_position_main').css({'opacity':''})
					resolve()
				}, 300)
			})
		}
	}).then(function() {
		cms_position_nav_busy = false
	}).catch(function() {
		$('.cms_position_main').css({'opacity':''})
		cms_position_nav_busy = false
	})

}

function cms_position_link_init($root){

	var $scope = $root ? $root.find('a[data-_pl="1"]') : $('a[data-_pl="1"]')

	$scope.not('.cms_position_link_ok').each(function(){

		var $link = $(this)

		$link.addClass('cms_position_link_ok')

		$link.on('click.cms', function(){

			var $this = $(this)

			// optional custom before/after on the link (override defaults)
			// Handlers must resolve with $(this) — the element they fire on.
			// After runs on a clone; resolving the original would remove the live link from the DOM.
			if (!$._data($this.get(0), 'events')['before']) {
				$this.on('before', function(){
					var $el = $(this)
					return new Promise(function(resolve) {
						$('.cms_position_main').css({'opacity':'0.5'})
						setTimeout(function() {
							resolve($el)
						}, 300)
					})
				})
			}

			if (!$._data($this.get(0), 'events')['after']) {
				$this.on('after', function(){
					var $el = $(this)
					return new Promise(function(resolve) {
						setTimeout(function() {
							$('.cms_position_main').css({'opacity':''})
							resolve($el)
						}, 300)
					})
				})
			}

			var download_page = new Promise(function(resolve) {
				get_ajax_positions($this.attr('href'), {'cms_positions': cms_position_collect()}, function(result) {
					resolve(result)
				})
			})

			var update_page = function(before_result) {
				return new Promise(function(resolve) {
					var $backup_this = before_result[0].clone(true, true)
					var result = before_result[1]

					cms_position_apply_result(result, {
						history: 'push',
						url: $this.attr('href')
					}).then(function() {
						// apply already cleared/re-inited; still resolve backup for after hook chain
						setTimeout(function() {
							resolve($backup_this)
						}, 100)
					})
				})
			}

			// animate is handled by before/after on the link; apply without double-dim
			cms_position_nav_busy = true

			Promise
				.all([$this.triggerHandler('before'), download_page])
				.then(update_page)
				.then(function($bu) {
					return $bu.triggerHandler('after')
				})
				.then(function($bu) {
					if ($bu && $bu.remove) {
						$bu.remove()
					}
				})
				.then(function() {
					cms_position_nav_busy = false
				})
				.catch(function() {
					$('.cms_position_main').css({'opacity':''})
					cms_position_nav_busy = false
				})

			return false

		})

	})

}

function cms_position_link_resize(){

}

function cms_position_on_popstate() {

	if (cms_position_nav_busy) {
		return
	}

	if (window.location.href === cms_position_last_url) {
		return
	}

	// Music unit set history (unit public slug) — see modules/music/js/units.js
	if (typeof music_set_on_popstate === 'function' && music_set_on_popstate()) {
		return
	}

	var url = window.location.pathname + window.location.search + window.location.hash
	cms_position_goto(url, {history: 'none', animate: true})

}

$(document).ready(function() {

	cms_position_last_url = window.location.href

	$(window).on('resize.cms', function(){
		cms_position_link_resize()
	})

	$(window).off('popstate.cms_position').on('popstate.cms_position', cms_position_on_popstate)

	cms_position_link_init()
	cms_position_link_resize()

})
