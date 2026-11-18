// Gears menu → Versions (cms_versions.md): the list popup, Compare on top of it, and Restore.

function cms_page_panel_versions_load($popup, cms_page_panel_id){

	$popup.find('.cms_popup_content').html('Loading...')

	get_ajax_panel('cms/cms_page_panel_versions', {
		'cms_do': 'cms_page_panel_versions_list',
		'version_panel_id': cms_page_panel_id,
	}, function(data){

		$popup.find('.cms_popup_content').html(data.result.cms_html)
		cms_popup_bind_cancel($popup)
		cms_page_panel_versions_bind($popup, cms_page_panel_id)

	})

}

function cms_page_panel_versions_compare(cms_page_panel_id, file){

	cms_popup_open_ajax('version_compare', function($compare){

		$compare.find('.cms_popup_content').html('Loading...')

		get_ajax_panel('cms/cms_page_panel_versions', {
			'cms_do': 'cms_page_panel_versions_compare',
			'version_panel_id': cms_page_panel_id,
			'version_file': file,
		}, function(data){
			$compare.find('.cms_popup_content').html(data.result.cms_html)
			cms_popup_bind_cancel($compare)
		})

	})

}

function cms_page_panel_versions_bind($popup, cms_page_panel_id){

	var $list = $popup.find('.cms_page_panel_versions_list')
	var $error = $popup.find('.cms_page_panel_versions_error')

	$popup.find('.cms_page_panel_versions_compare_button').on('click.cms', function(){
		cms_page_panel_versions_compare(cms_page_panel_id, $(this).closest('.cms_page_panel_versions_row').attr('data-version_file'))
	})

	$popup.find('.cms_page_panel_versions_restore_button').on('click.cms', function(){

		if ($list.hasClass('cms_page_panel_versions_busy')){
			return
		}

		var file = $(this).closest('.cms_page_panel_versions_row').attr('data-version_file')
		var fail = function(message){
			$list.removeClass('cms_page_panel_versions_busy')
			$error.text(message || 'The version could not be restored.')
		}

		$error.text('')
		$list.addClass('cms_page_panel_versions_busy')

		get_ajax('cms/cms_page_panel_versions', {
			'cms_do': 'cms_page_panel_versions_restore',
			'version_panel_id': cms_page_panel_id,
			'version_file': file,
		}).then(function(data){
			var result = (data && data.result) || {}
			if (String(result.ok) !== '1'){
				fail(result.message)
				return
			}
			// The editor form shows what was saved before: load it again with the restored values
			window.location.reload()
		}, function(){
			fail('')
		})

	})

}

function cms_page_panel_button_versions_init($root){

	var $scope = $root ? $root.find('.cms_page_panel_versions_button') : $('.cms_page_panel_versions_button')

	$scope.not('.cms_page_panel_button_versions_ok').each(function(){

		var $button = $(this)
		$button.addClass('cms_page_panel_button_versions_ok')

		$button.on('click.cms', function(e){

			e.preventDefault()
			e.stopPropagation()

			var cms_page_panel_id = $(this).data('cms_page_panel_id')

			cms_popup_open_ajax('versions', function($popup){
				cms_page_panel_versions_load($popup, cms_page_panel_id)
			})

		})

	})

}

$(document).ready(function() {

	cms_page_panel_button_versions_init()

})
