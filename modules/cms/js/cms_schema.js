function cms_schema_replace_container($from, html){

	var $wrap = $(html)
	var $new = $wrap.filter('.cms_schema_container').add($wrap.find('.cms_schema_container')).first()
	if (!$new.length){
		$new = $wrap
	}

	var $old = $from && $from.length
		? $from.closest('.cms_schema_container')
		: $('.cms_schema_container').first()

	if ($old.length){
		$old.replaceWith($new)
	}

	return $new

}

function cms_schema_request_context($el){

	var $container = $el.closest('.cms_schema_container')
	var module = $container.attr('data-module') || ''
	var fragment_raw = $container.attr('data-fragment')
	var fragment = (fragment_raw === '1' || fragment_raw === 1 || fragment_raw === 'true') ? 1 : 0

	return {
		$container: $container,
		module: module,
		fragment: fragment
	}

}

function cms_schema_init($root) {

	var $scope = $root
		? $root.find('.cms_schema_container').addBack('.cms_schema_container')
		: $('.cms_schema_container')

	$scope.not('.cms_schema_ok').each(function(){

		var $container = $(this)

		$container.addClass('cms_schema_ok')

		$('.cms_schema_fix', $container).on('click.cms', function(e) {
			e.preventDefault()

			var $this = $(this)
			var key = $this.data('key')

			if (!key) {
				return
			}

			var ctx = cms_schema_request_context($this)

			$this.addClass('cms_disabled').text('fixing...')

			var data = {
				do: 'fix_schema',
				key: key
			}
			if (ctx.fragment){
				data.fragment = 1
			}
			// Never rely on "module" alone — panel() overwrites it with package name "cms"
			if (ctx.module){
				data.schema_module = ctx.module
				data.filter_module = ctx.module
			}

			get_ajax_panel('cms/cms_schema', data, function(result) {
				var res = result && result.result ? result.result : {}
				if (res._html) {
					var $new = cms_schema_replace_container(ctx.$container, res._html)
					cms_schema_init($new.parent())
				}
				if (res.success) {
					cms_notification('Schema fixed successfully', 4, 'success')
				}
			})
		})

		$('.cms_schema_dump_structure', $container).on('click.cms', function(e) {
			e.preventDefault()

			var $this = $(this)
			var ctx = cms_schema_request_context($this)
			var label = $this.text()

			$this.addClass('cms_disabled').text('…')

			var data = {
				do: 'dump_cms_structure'
			}
			if (ctx.fragment){
				data.fragment = 1
			}
			if (ctx.module){
				data.schema_module = ctx.module
				data.filter_module = ctx.module
			}

			get_ajax_panel('cms/cms_schema', data, function(result) {
				var res = result && result.result ? result.result : {}
				if (res._html) {
					var $new = cms_schema_replace_container(ctx.$container, res._html)
					cms_schema_init($new.parent())
				}
				if (res.success == 1 || res.success === true) {
					cms_notification(res.message || 'Structure dump ready', 4, 'success')
				} else {
					$this.removeClass('cms_disabled').text(label)
				}
			})
		})

		$('.cms_schema_sync', $container).on('click.cms', function(e) {
			e.preventDefault()

			var $this = $(this)
			var module = $this.data('module') || $this.attr('data-module')

			if (!module) {
				return
			}

			if (!confirm('Synchronise panel table data for module "' + module + '"?\n\nThis copies table fields from params into panel tables and removes migrated param rows. Ensure you have a database backup first.')) {
				return
			}

			var ctx = cms_schema_request_context($this)

			$this.addClass('cms_disabled').text('syncing...')

			var data = {
				do: 'sync_panel_tables',
				schema_module: module,
				module: module
			}
			if (ctx.fragment){
				data.fragment = 1
			}
			if (ctx.module){
				data.filter_module = ctx.module
				data.schema_module = ctx.module
			}

			get_ajax_panel('cms/cms_schema', data, function(result) {
				var res = result && result.result ? result.result : {}
				var stats = res.stats || {}
				var ok = res.success == 1 || res.success === true || (Array.isArray(stats.errors) && stats.errors.length === 0 && (stats.synced > 0 || stats.skipped > 0))

				if (res._html) {
					var $new = cms_schema_replace_container(ctx.$container, res._html)
					cms_schema_init($new.parent())
				}
				if (ok) {
					cms_notification(res.message || 'Panel tables synchronised', 5, 'success')
				}
			})
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
