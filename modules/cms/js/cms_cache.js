function cms_cache_hydrate($root) {

	var $mounts = $root ? $root.find('[data-cache_ajax]') : $('[data-cache_ajax]');

	$mounts.not('.cms_cache_ok').each(function() {

		var $mount = $(this)

		var panel = $mount.data('cache_ajax')

		if (!panel) {
			return
		}

		$mount.addClass('cms_cache_ok')

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

function cms_cache_init($root) {

	cms_cache_hydrate($root)

	if (!window.cms_cache_position_hook_ok) {
		window.cms_cache_position_hook_ok = true
		if (!window.cms_position_link_after) {
			window.cms_position_link_after = []
		}
		window.cms_position_link_after.push(function() {
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