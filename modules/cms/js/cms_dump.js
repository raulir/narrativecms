function cms_dump_sync_fake_checks($root){

	$root.find('.cms_dump_toggle_row').each(function(){

		var $row = $(this)
		var $input = $row.find('input[type=checkbox]')
		var $fake = $row.find('.cms_dump_fake_check')

		if (!$input.length || !$fake.length){
			return
		}

		if ($input.is(':checked')){
			$fake.text('[v]')
		} else {
			$fake.text('[ ]')
		}

	})

}

function cms_dump_sync_year_checks($container){

	$container.find('.cms_dump_year_block').each(function(){

		var $block = $(this)
		var $months = $block.find('.cms_dump_month_check')
		var $year = $block.find('.cms_dump_year_check')

		if (!$months.length || !$year.length){
			return
		}

		var all_on = true
		$months.each(function(){
			if (!$(this).is(':checked')){
				all_on = false
				return false
			}
		})

		$year.prop('checked', all_on)

	})

	cms_dump_sync_fake_checks($container)

}

function cms_dump_init($root){

	var $scope = $root
		? $root.find('.cms_dump_container').addBack('.cms_dump_container')
		: $('.cms_dump_container')

	$scope.not('.cms_dump_ok').each(function(){

		var $container = $(this)
		$container.addClass('cms_dump_ok')

		cms_dump_sync_year_checks($container)

		$container.find('.cms_dump_options_btn').on('click.cms', function(){

			var $options = $container.find('.cms_dump_options')
			var $btn = $(this)

			if ($options.is(':visible')){
				$options.hide()
				$btn.removeClass('cms_tool_button_active')
			} else {
				$options.show()
				$btn.addClass('cms_tool_button_active')
			}

		})

		$container.find('.cms_dump_backups_btn').on('click.cms', function(){

			var $body = $container.find('.cms_dump_backups_body')
			var $btn = $(this)

			if ($body.is(':visible')){
				$body.hide()
				$btn.removeClass('cms_tool_button_active')
			} else {
				$body.show()
				$btn.addClass('cms_tool_button_active')
			}

		})

		$container.find('.cms_dump_fake_check, .cms_dump_check_label').on('click.cms', function(){

			var $row = $(this).closest('.cms_dump_toggle_row')
			var $input = $row.find('input[type=checkbox]')

			if (!$input.length || $input.prop('disabled')){
				return
			}

			var next = !$input.is(':checked')
			$input.prop('checked', next)

			// Year master: select / clear all months under this year
			if ($input.hasClass('cms_dump_year_check')){
				var $block = $row.closest('.cms_dump_year_block')
				$block.find('.cms_dump_month_check').prop('checked', next)
				cms_dump_sync_fake_checks($container)
				return
			}

			// Month toggle: refresh year master for that block
			if ($input.hasClass('cms_dump_month_check')){
				cms_dump_sync_year_checks($container)
				return
			}

			cms_dump_sync_fake_checks($container)

		})

		// Typing the resize size must not toggle the checkbox
		$container.find('.cms_dump_resize_px').on('click.cms mousedown.cms', function(e){
			e.stopPropagation()
		})

		$container.find('.cms_dump_generate_btn').on('click.cms', function(){

			var $btn = $(this)
			if ($btn.hasClass('cms_dump_generate_busy')){
				return
			}

			$btn.addClass('cms_dump_generate_busy cms_tool_button_inactive')
			$container.find('.cms_dump_generate_form').trigger('submit')

		})

		$container.find('.cms_dump_upload_btn').on('click.cms', function(){

			var $form = $container.find('.cms_dump_upload_form')
			var $file = $form.find('.cms_dump_upload_file')
			if (!$file.length || !$file[0].files || !$file[0].files.length){
				window.alert('Choose a .zip file to upload first.')
				return
			}
			var $btn = $(this)
			if ($btn.hasClass('cms_dump_upload_busy')){
				return
			}
			$btn.addClass('cms_dump_upload_busy cms_tool_button_inactive')
			$form.trigger('submit')

		})

		$container.find('.cms_dump_restore_btn').on('click.cms', function(){

			if (!window.confirm('Restore will overwrite matching tables and extract resources from this backup. Continue?')){
				return
			}
			$(this).closest('form').trigger('submit')

		})

		$container.find('.cms_dump_download_btn').on('click.cms', function(){

			$(this).closest('form').trigger('submit')

		})

		$container.find('.cms_dump_delete_btn').on('click.cms', function(){

			if (!window.confirm('Delete this backup from the server? This cannot be undone.')){
				return
			}
			$(this).closest('form').trigger('submit')

		})

	})

}

$(function(){

	cms_dump_init()

})
