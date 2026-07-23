function search_cms_purge_set_status($root, text){

	$root.find('.search_cms_purge_status').text(text || '')

}

function search_cms_purge_start($root){

	if ($root.data('search_purge_busy')){
		return
	}

	$root.data('search_purge_busy', 1)
	$root.find('.search_cms_purge_button').addClass('search_cms_purge_busy')
	search_cms_purge_set_status($root, 'Purging...')

	get_ajax_panel('search/search_cms_purge', {
		'do': 'purge',
		'no_html': '1'
	}, function(data){

		$root.data('search_purge_busy', 0)
		$root.find('.search_cms_purge_button').removeClass('search_cms_purge_busy')

		var result = (data && data.result) ? data.result : {}
		if (result.result && typeof result.result === 'object'){
			result = result.result
		}

		search_cms_purge_set_status($root, result.text || 'Done')

	})

}

function search_cms_purge_init($root){

	var $scope = $root ? $root.find('.search_cms_purge') : $('.search_cms_purge')

	$scope.not('.search_cms_purge_ok').each(function(){

		var $el = $(this)
		$el.addClass('search_cms_purge_ok')

		$el.find('.search_cms_purge_button').on('click.cms', function(){
			search_cms_purge_start($el)
		})

	})

}

function search_cms_purge_resize(){

}

$(document).ready(function(){

	$(window).on('resize.cms', search_cms_purge_resize)

	search_cms_purge_init()
	search_cms_purge_resize()

})
