function cms_schema_init() {
	$('.cms_schema_fix').on('click.cms', function(e) {
		e.preventDefault()
		
		var $this = $(this)
		var key = $this.data('key')
		
		if (!key) {
			return
		}
		
		$this.addClass('cms_disabled').text('fixing...')
		
		var data = {
			do: 'fix_schema',
			key: key
		}
		
		get_ajax_panel('cms/cms_schema', data, function(result) {
			if (result && result.result && result.result.success) {
				$('.cms_schema_container').parent().html(result.result._html)
				cms_notification('Schema fixed successfully', 4, 'success')
			} else {
				$this.removeClass('cms_disabled').text('fix')
				var msg = 'Fix failed'
				if (result && result.result && result.result.message) {
					msg += ': ' + result.result.message
				}
				cms_error(msg, 6)
			}
		})
	})

	$('.cms_schema_sync').on('click.cms', function(e) {
		e.preventDefault()

		var $this = $(this)
		var module = $this.data('module')

		if (!module) {
			return
		}

		if (!confirm('Synchronise panel table data for module "' + module + '"?\n\nThis copies table fields from params into panel tables and removes migrated param rows. Ensure you have a database backup first.')) {
			return
		}

		$this.addClass('cms_disabled').text('syncing...')

		var data = {
			do: 'sync_panel_tables',
			module: module
		}

		get_ajax_panel('cms/cms_schema', data, function(result) {
			var res = result && result.result ? result.result : {}
			var stats = res.stats || {}
			var ok = res.success == 1 || res.success === true || (Array.isArray(stats.errors) && stats.errors.length === 0 && (stats.synced > 0 || stats.skipped > 0))

			if (ok) {
				$('.cms_schema_container').parent().html(res._html)
				cms_notification(res.message || 'Panel tables synchronised', 5, 'success')
			} else {
				$this.removeClass('cms_disabled').text('sync panel tables')
				cms_error(res.message || 'Sync failed', 8)
			}
		})
	})
}

function cms_schema_resize() {
	
}

function cms_schema_scroll() {
	
}

$(document).ready(function() {
	$(window).on('resize.cms', cms_schema_resize)
	$(window).on('scroll.cms', cms_schema_scroll)
	
	cms_schema_init()
	cms_schema_resize()
	cms_schema_scroll()
})