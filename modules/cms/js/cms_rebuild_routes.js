function cms_rebuild_routes_set_status($root, text){

	$root.find('.cms_rebuild_routes_status').text(text || '')

}

function cms_rebuild_routes_run($root){

	if ($root.data('cms_rebuild_routes_busy')){
		return
	}

	if (!window.confirm('Rebuild all public URL routes (cms_route) from pages and list titles? Current table is backed up as a zip under cache/db/ first.')){
		return
	}

	$root.data('cms_rebuild_routes_busy', 1)
	$root.find('.cms_rebuild_routes_button').addClass('cms_rebuild_routes_busy')
	cms_rebuild_routes_set_status($root, 'Rebuilding…')

	get_ajax_panel('cms/cms_rebuild_routes', {
		'do': 'rebuild_routes',
		'no_html': '1'
	}, function(data){

		$root.data('cms_rebuild_routes_busy', 0)
		$root.find('.cms_rebuild_routes_button').removeClass('cms_rebuild_routes_busy')

		var result = (data && data.result) ? data.result : {}
		if (result.result && typeof result.result === 'object'){
			result = result.result
		}

		if (!result || !result.ok){
			cms_rebuild_routes_set_status($root, (result && result.error) ? result.error : 'Rebuild failed')
			return
		}

		var text = 'Done: ' + (result.pages || 0) + ' pages, ' + (result.list_items || 0) + ' list items'
		if (result.backup){
			text += ' (backup ' + result.backup + ')'
		}
		cms_rebuild_routes_set_status($root, text)

	})

}

function cms_rebuild_routes_init($root){

	var $scope = $root ? $root.find('.cms_rebuild_routes') : $('.cms_rebuild_routes')

	$scope.not('.cms_rebuild_routes_ok').each(function(){

		var $el = $(this)
		$el.addClass('cms_rebuild_routes_ok')

		$el.find('.cms_rebuild_routes_button').on('click.cms', function(){
			cms_rebuild_routes_run($el)
		})

	})

}

$(function(){

	cms_rebuild_routes_init()

})
