function shopify_cms_reload_set_status($root, text){

	$root.find('.shopify_cms_reload_status').text(text || '')

}

function shopify_cms_reload_start($root){

	if ($root.data('shopify_reload_busy')){
		return
	}

	$root.data('shopify_reload_busy', 1)
	$root.find('.shopify_cms_reload_button').addClass('shopify_cms_reload_busy')
	shopify_cms_reload_set_status($root, 'Marking products...')

	get_ajax_panel('shopify/shopify_cms_reload', {
		'do': 'reload_clear',
		'no_html': '1'
	}, function(data){

		$root.data('shopify_reload_busy', 0)
		$root.find('.shopify_cms_reload_button').removeClass('shopify_cms_reload_busy')

		var result = (data && data.result) ? data.result : {}
		if (result.result && typeof result.result === 'object'){
			result = result.result
		}

		shopify_cms_reload_set_status($root, result.text || 'Done')

	})

}

function shopify_cms_reload_init($root){

	var $scope = $root ? $root.find('.shopify_cms_reload') : $('.shopify_cms_reload')

	$scope.not('.shopify_cms_reload_ok').each(function(){

		var $el = $(this)
		$el.addClass('shopify_cms_reload_ok')

		$el.find('.shopify_cms_reload_button').on('click.cms', function(){
			shopify_cms_reload_start($el)
		})

	})

}

function shopify_cms_reload_resize(){

}

$(document).ready(function(){

	$(window).on('resize.cms', shopify_cms_reload_resize)

	shopify_cms_reload_init()
	shopify_cms_reload_resize()

})
