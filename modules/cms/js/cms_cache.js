function cms_cache_hydrate() {

	$('[data-cache_ajax]').each(function() {

		var $mount = $(this)

		if ($mount.data('cache_ajax_ok')) {
			return
		}

		var panel = $mount.data('cache_ajax')

		if (!panel) {
			return
		}

		$mount.data('cache_ajax_ok', 1)

		get_ajax_panel(panel, {}, function(result) {
			if (result && result.result && result.result.html) {
				$mount.replaceWith(result.result.html)
				if (typeof cms_position_link_init === 'function') {
					cms_position_link_init()
				}
			}
		})

	})

}

function cms_cache_init() {

	cms_cache_hydrate()

	if (!window.cms_cache_position_hook_ok) {
		window.cms_cache_position_hook_ok = true
		if (typeof cms_position_link_after === 'undefined') {
			cms_position_link_after = []
		}
		cms_position_link_after.push(function() {
			cms_cache_init()
			cms_cache_resize()
			cms_cache_scroll()
		})
	}

}

function cms_cache_resize() {

}

function cms_cache_scroll() {

}

$(document).ready(function() {

	$(window).on('resize.cms', cms_cache_resize)
	$(window).on('scroll.cms', cms_cache_scroll)

	cms_cache_init()
	cms_cache_resize()
	cms_cache_scroll()

})