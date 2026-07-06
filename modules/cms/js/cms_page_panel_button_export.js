var cms_page_panel_export_preview_timer = null

function cms_page_panel_export_overlay_show($container){

	var $overlay = $container.find('.cms_page_panel_export_overlay')
	$overlay.addClass('cms_page_panel_export_overlay_visible')
	setTimeout(function(){
		if ($overlay.hasClass('cms_page_panel_export_overlay_visible')){
			$overlay.addClass('cms_page_panel_export_overlay_show_label')
		}
	}, 300)

}

function cms_page_panel_export_overlay_hide($container){

	var $overlay = $container.find('.cms_page_panel_export_overlay')
	$overlay.removeClass('cms_page_panel_export_overlay_show_label cms_page_panel_export_overlay_visible')

}

function cms_page_panel_export_sync_fake_checks($popup){

	$popup.find('.cms_page_panel_export_toggle_row').each(function(){

		var $row = $(this)
		var $input = $row.find('input[type=checkbox]')
		var $fake = $row.find('.cms_page_panel_export_fake_check')

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

function cms_page_panel_export_sync_affected_dim($popup){

	var $img_size = $popup.find('.cms_page_panel_export_optim_images_affected')
	var $vid_size = $popup.find('.cms_page_panel_export_optim_videos_affected')

	$img_size.toggleClass('cms_page_panel_export_affected_dim', !$popup.find('[name=optimised_images]').is(':checked'))
	$vid_size.toggleClass('cms_page_panel_export_affected_dim', !$popup.find('[name=optimised_videos]').is(':checked'))

}

function cms_page_panel_export_collect_options($container){

	var $root = $container.find('.cms_page_panel_export_settings_container')
	if (!$root.length){
		$root = $container
	}

	return {
		'include_database': $root.find('[name=include_database]').is(':checked') ? '1' : '0',
		'include_files': $root.find('[name=include_files]').is(':checked') ? '1' : '0',
		'optimised_images': $root.find('[name=optimised_images]').is(':checked') ? '1' : '0',
		'image_cutoff_px': $root.find('[name=image_cutoff_px]').val(),
		'optimised_videos': $root.find('[name=optimised_videos]').is(':checked') ? '1' : '0',
		'video_quality': $root.find('[name=video_quality]').val(),
		'include_panel_files': $root.find('[name=include_panel_files]').is(':checked') ? '1' : '0',
	}

}

function cms_page_panel_export_update_preview($popup, export_id){

	var opts = cms_page_panel_export_collect_options($popup)

	get_ajax('cms/cms_page_panel_export', Object.assign({
		'export_id': export_id,
		'do': 'cms_page_panel_export_preview',
		'no_html': '1',
	}, opts)).then(function(data){

		if (!data.result || data.result.result !== 'ok'){
			return
		}

		var r = data.result

		$popup.find('.cms_page_panel_export_database_size').text(r.database_size || '—')
		$popup.find('.cms_page_panel_export_files_size').text(r.files_size || '—')
		$popup.find('.cms_page_panel_export_panel_files_size').text(r.panel_files_size || '—')
		$popup.find('.cms_page_panel_export_total_size').text(r.total_size || '—')

		var img_n = parseInt(r.oversized_images_count, 10) || 0
		var vid_n = parseInt(r.optimised_videos_count, 10) || 0

		$popup.find('.cms_page_panel_export_optim_images_affected').text(img_n ? img_n + ' affected' : 'none')
		$popup.find('.cms_page_panel_export_optim_videos_affected').text(vid_n ? vid_n + ' affected' : 'none')

		cms_page_panel_export_sync_fake_checks($popup)
		cms_page_panel_export_sync_affected_dim($popup)

	})

}

function cms_page_panel_export_schedule_preview($popup, export_id){

	if (cms_page_panel_export_preview_timer){
		clearTimeout(cms_page_panel_export_preview_timer)
	}

	cms_page_panel_export_preview_timer = setTimeout(function(){
		cms_page_panel_export_update_preview($popup, export_id)
	}, 250)

}

function cms_page_panel_export_bind_settings($popup, export_id){

	$popup.find('.cms_page_panel_export_fake_check, .cms_page_panel_export_settings_text').off('click.cms').on('click.cms', function(){

		var $row = $(this).closest('.cms_page_panel_export_toggle_row')
		if ($row.hasClass('cms_page_panel_export_disabled')){
			return
		}

		var $input = $row.find('input[type=checkbox]')
		if (!$input.length || $input.prop('disabled')){
			return
		}

		$input.prop('checked', !$input.is(':checked')).trigger('change')

	})

	$popup.find('.cms_page_panel_export_opt, .cms_page_panel_export_image_cutoff, .cms_page_panel_export_video_quality').off('change.cms input.cms').on('change.cms input.cms', function(){

		var files_on = $popup.find('[name=include_files]').is(':checked')
		$popup.find('.cms_page_panel_export_optim_images_row, .cms_page_panel_export_optim_videos_row').toggleClass('cms_page_panel_export_disabled', !files_on)
		if (!files_on){
			$popup.find('[name=optimised_images], [name=optimised_videos]').prop('checked', false)
		}

		cms_page_panel_export_sync_fake_checks($popup)
		cms_page_panel_export_sync_affected_dim($popup)
		cms_page_panel_export_schedule_preview($popup, export_id)

	})

	$popup.find('.cms_page_panel_export_run').off('click.cms').on('click.cms', function(){

		var $settings = $popup.find('.cms_page_panel_export_settings_container')
		var opts = cms_page_panel_export_collect_options($popup)

		cms_page_panel_export_overlay_show($settings)

		get_ajax_panel('cms/cms_page_panel_export', Object.assign({
			'export_id': export_id,
			'do': 'cms_page_panel_export',
		}, opts), function(data){

			cms_page_panel_export_overlay_hide($settings)
			$popup.find('.cms_popup_content').html(data.result._html)

			cms_popup_bind_cancel($popup)

			$popup.find('.cms_page_panel_export_close').on('click.cms', function(){
				cms_popup_close($popup)
			})

		})

	})

	cms_page_panel_export_sync_fake_checks($popup)
	cms_page_panel_export_sync_affected_dim($popup)
	cms_page_panel_export_update_preview($popup, export_id)

}

function cms_page_panel_button_export_init(){

	$('.cms_page_panel_export').off('click.cms').on('click.cms', function(e){

		e.preventDefault()
		e.stopPropagation()

		var cms_page_panel_id = $(this).data('cms_page_panel_id')

		cms_popup_open_ajax('export', function($popup){

			$popup.find('.cms_popup_content').html('Loading...')

			get_ajax_panel('cms/cms_page_panel_export', {
				'export_id': cms_page_panel_id,
				'do': 'cms_page_panel_export_settings',
			}, function(data){

				$popup.find('.cms_popup_content').html(data.result._html)
				cms_popup_bind_cancel($popup)
				cms_page_panel_export_bind_settings($popup, cms_page_panel_id)

			})

		})

	})

}

function cms_page_panel_button_export_resize(){

}

function cms_page_panel_button_export_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', cms_page_panel_button_export_resize)
	$(window).on('scroll.cms', cms_page_panel_button_export_scroll)

	cms_page_panel_button_export_init()
	cms_page_panel_button_export_resize()
	cms_page_panel_button_export_scroll()

})