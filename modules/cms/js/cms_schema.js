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