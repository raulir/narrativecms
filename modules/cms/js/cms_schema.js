function cms_schema_init($root) {

	var $scope = $root ? $root.find('.cms_schema_container') : $('.cms_schema_container');

	$scope.not('.cms_schema_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_schema_ok');

		$('.cms_schema_fix', $container).on('click.cms', function(e) {
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
			var res = result && result.result ? result.result : {}
			if (res._html) {
				$('.cms_schema_container').parent().html(res._html)
				cms_schema_init()
			}
			if (res.success) {
				cms_notification('Schema fixed successfully', 4, 'success')
			}
		})
		})

		$('.cms_schema_sync', $container).on('click.cms', function(e) {
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

			if (res._html) {
				$('.cms_schema_container').parent().html(res._html)
				cms_schema_init()
			}
			if (ok) {
				cms_notification(res.message || 'Panel tables synchronised', 5, 'success')
			}
		})
		})

	});

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