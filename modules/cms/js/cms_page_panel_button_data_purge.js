/**
 * Gear menu → Data purge: orphan panel/settings fields + dead translation languages.
 */

function cms_page_panel_data_purge_sync_fake_checks($root){

	$root.find('.cms_page_panel_data_purge_row').each(function(){

		var $row = $(this)
		var $input = $row.find('input[type=checkbox]')
		var $fake = $row.find('.cms_page_panel_data_purge_fake_check')
		if (!$input.length || !$fake.length){
			return
		}
		$fake.text($input.is(':checked') ? '[v]' : '[ ]')

	})

}

function cms_page_panel_data_purge_bind($popup, cms_page_panel_id){

	cms_popup_bind_cancel($popup)

	var $root = $popup.find('.cms_page_panel_data_purge_container')
	cms_page_panel_data_purge_sync_fake_checks($root)

	// Toggle like export fake checks
	$root.find('.cms_page_panel_data_purge_fake_check, .cms_page_panel_data_purge_settings_text')
			.off('click.cms').on('click.cms', function(e){

		e.preventDefault()
		e.stopPropagation()
		var $row = $(this).closest('.cms_page_panel_data_purge_row')
		var $input = $row.find('input[type=checkbox]')
		if (!$input.length){
			return
		}
		$input.prop('checked', !$input.is(':checked'))
		cms_page_panel_data_purge_sync_fake_checks($root)

	})

	$popup.find('.cms_page_panel_data_purge_run').off('click.cms').on('click.cms', function(e){

		e.preventDefault()
		e.stopPropagation()

		var panel_fields = []
		var settings_fields = []
		var languages = []

		$root.find('input[name="panel_fields[]"]:checked').each(function(){
			panel_fields.push(String($(this).val() || ''))
		})
		$root.find('input[name="settings_fields[]"]:checked').each(function(){
			settings_fields.push(String($(this).val() || ''))
		})
		$root.find('input[name="languages[]"]:checked').each(function(){
			languages.push(String($(this).val() || ''))
		})

		if (!panel_fields.length && !settings_fields.length && !languages.length){
			if (typeof cms_notification === 'function'){
				cms_notification('Nothing selected', 2)
			}
			return
		}

		// Flatten for PHP: panel_fields[0], etc. (reliable array POST)
		var data = {
			'do': 'cms_page_panel_data_purge',
			'cms_page_panel_id': cms_page_panel_id
		}
		for (var i = 0; i < panel_fields.length; i++){
			data['panel_fields[' + i + ']'] = panel_fields[i]
		}
		for (var j = 0; j < settings_fields.length; j++){
			data['settings_fields[' + j + ']'] = settings_fields[j]
		}
		for (var k = 0; k < languages.length; k++){
			data['languages[' + k + ']'] = languages[k]
		}

		get_ajax_panel('cms/cms_page_panel_data_purge', data, function(result){

			var html = ''
			if (result && result.result && result.result._html){
				html = result.result._html
			} else if (result && result._html){
				html = result._html
			} else if (result && result.result && result.result.html){
				html = result.result.html
			}

			if (html){
				$popup.find('.cms_popup_content').html(html)
				cms_page_panel_data_purge_bind($popup, cms_page_panel_id)
			}
			if (typeof cms_notification === 'function'){
				cms_notification('Purged', 2)
			}

		})

	})

}

function cms_page_panel_button_data_purge_init($root){

	var $scope = $root ? $root.find('.cms_page_panel_data_purge') : $('.cms_page_panel_data_purge')

	$scope.not('.cms_page_panel_button_data_purge_ok').each(function(){

		var $button = $(this)
		$button.addClass('cms_page_panel_button_data_purge_ok')

		$button.off('click.cms').on('click.cms', function(e){

			e.preventDefault()
			e.stopPropagation()

			var cms_page_panel_id = parseInt($(this).attr('data-cms_page_panel_id') || '0', 10) || 0
			if (cms_page_panel_id < 1){
				return
			}

			cms_popup_open_ajax('data_purge', function($popup){

				$popup.find('.cms_popup_content').html('Loading...')

				get_ajax_panel('cms/cms_page_panel_data_purge', {
					'cms_page_panel_id': cms_page_panel_id
				}, function(data){

					var html = ''
					if (data && data.result && data.result._html){
						html = data.result._html
					} else if (data && data._html){
						html = data._html
					} else if (data && data.result && data.result.html){
						html = data.result.html
					}
					$popup.find('.cms_popup_content').html(html || 'Error loading purge scan')
					cms_page_panel_data_purge_bind($popup, cms_page_panel_id)

				})

			})

		})

	})

}

$(document).ready(function(){
	cms_page_panel_button_data_purge_init()
})
