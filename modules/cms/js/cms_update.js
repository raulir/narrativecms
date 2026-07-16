function cms_update_popup_files($popup){

	return $popup.find('.cms_update_popup_files')

}

function cms_update_popup_overlay_show($popup){

	var $overlay = $popup.find('.cms_update_popup_overlay')
	$overlay.addClass('cms_update_popup_overlay_visible')
	setTimeout(function(){
		if ($overlay.hasClass('cms_update_popup_overlay_visible')){
			$overlay.addClass('cms_update_popup_overlay_show_label')
		}
	}, 1000)

}

function cms_update_popup_overlay_hide($popup){

	var $overlay = $popup.find('.cms_update_popup_overlay')
	$overlay.removeClass('cms_update_popup_overlay_show_label cms_update_popup_overlay_visible')

}

function cms_update_popup_finish($popup, state){

	cms_update_popup_overlay_hide($popup)
	state.allow_dismiss = true
	$popup.find('.cms_update_popup_close').show()

}

function cms_update_apply_row_html(area, row_html){

	var $table = $('.cms_update_table_installed')
	if (!$table.length){
		$table = $('.cms_update_table').not('.cms_update_table_available').first()
	}

	var $existing = $table.find('.cms_update_row').filter(function(){
		return String($(this).data('area') || '') === String(area || '')
	})

	if ($existing.length){
		$existing.replaceWith(row_html)
	} else {
		$table.append(row_html)
	}

	// Remove from available-to-install table if present
	var $available = $('.cms_update_table_available')
	if ($available.length){
		$available.find('.cms_update_row').filter(function(){
			return String($(this).data('area') || '') === String(area || '')
		}).remove()

		if ($available.find('.cms_update_row[data-area]').length === 0){
			$available.prev('.cms_update_section_label').remove()
			$available.remove()
		}
	}

}

function cms_update_confirm_area(area, $popup, state, options){

	options = options || {}

	cms_update_popup_overlay_show($popup)

	get_ajax('cms/cms_update', {
		'do': 'cms_update_confirm',
		'area': area,
		'success': function(data){

			var result = (data.result && data.result.result) ? data.result.result : {}

			if (result.error && !result.row_html){
				cms_update_popup_finish($popup, state)
				cms_notification('Update finished but confirming module failed', 6)
				return
			}

			if (result.row_html){
				cms_update_apply_row_html(area, result.row_html)
			}

			if (options.after_confirm){
				options.after_confirm(result)
			}

			cms_update_popup_finish($popup, state)

			if (options.reload){
				window.location.reload()
			}

		}
	})

}

function cms_update_run_pipeline(area, options){

	options = options || {}
	var after_cleanup = options.after_cleanup || null
	var done_message = options.done_message || 'CMS updated'
	var reload_after = !!options.reload_after

	var state = {
		allow_dismiss: false
	}

	get_ajax_panel('cms/cms_update_popup', {
		'_no_js': '1'
	}, function(data){

		panels_display_popup(data.result._html, {
			'cancel': function(after){
				if (state.allow_dismiss){
					after()
				}
			}
		})

		var $popup = $('.cms_update_popup_container').last()
		var $files = cms_update_popup_files($popup)

		$files.html('Getting list of files ...')

		get_ajax('cms/cms_update', {
			'do': 'cms_update_list',
			'area': area,
			'success': function(data){

				var files = (data.result && data.result.result) ? data.result.result : []
				if (!files.length){
					$files.html('No files to transfer.')
					if (after_cleanup){
						after_cleanup($popup, state)
					} else {
						cms_notification(done_message, 5)
						cms_update_confirm_area(area, $popup, state, {
							'reload': reload_after
						})
					}
					return
				}

				// print out list of files
				$files.html('')
				$.each(files, function(index, value){
					$files.append(
						'<div class="cms_update_result_file cms_update_result_file_' + value.fn_hash + '">' +
							'<span class="cms_update_tick">(<span style="font-weight: bold; color: #d0d0d0; ">' + value.letter + '</span>)</span>&nbsp;' +
							value.filename +
						'</div>'
					)
					files[index].updated = 0
				})

				// Batch transfer: up to batch_size files per ajax, max_batches in flight
				var batch_size = 20
				var max_batches = 2
				var batches_active = 0
				var transfer_done = false

				function cms_update_mark_file(fn_hash, letter, colour){
					$files.find('.cms_update_result_file_' + fn_hash).children('.cms_update_tick')
							.html('(<span style="color: ' + colour + '; font-weight: bold; ">' + letter + '</span>)')
				}

				function cms_update_take_batch(){
					var batch = []
					$.each(files, function(key, value){
						if (value.updated == 0 && batch.length < batch_size){
							files[key].updated = 1
							batch.push(value)
						}
					})
					return batch
				}

				function cms_update_pending_left(){
					var pending = false
					$.each(files, function(key, value){
						if (value.updated == 0){
							pending = true
						}
					})
					return pending
				}

				function cms_update_finish_transfer(){
					if (transfer_done){
						return
					}
					transfer_done = true

					get_ajax('cms/cms_update', {
						'do': 'cms_update_copy',
						'area': area,
						'success': function(){

							get_ajax('cms/cms_update', {
								'do': 'cms_update_cleanup',
								'area': area,
								'success': function(){

									if (after_cleanup){
										after_cleanup($popup, state)
									} else {
										cms_notification(done_message, 5)
										cms_update_confirm_area(area, $popup, state, {
											'reload': reload_after
										})
									}

								}
							})

						}
					})
				}

				function cms_update_pump_batches(){

					while (batches_active < max_batches){

						var batch = cms_update_take_batch()
						if (!batch.length){
							break
						}

						batches_active = batches_active + 1

						var filenames = []
						var letter_by_hash = {}
						$.each(batch, function(_, value){
							filenames.push(value.filename)
							letter_by_hash[value.fn_hash] = value.letter
							cms_update_mark_file(value.fn_hash, value.letter, 'orange')
						})

						get_ajax('cms/cms_update', {
							'do': 'cms_update_file',
							'area': area,
							'filenames': filenames,
							'success': function(data){

								var done = data.result && data.result.result && data.result.result.done
										? data.result.result.done
										: []

								if (done.length){
									$.each(done, function(_, row){
										var h = row.fn_hash
										var letter = letter_by_hash[h] || 'U'
										if (h){
											cms_update_mark_file(h, letter, 'green')
										}
									})
								} else {
									// Unexpected empty — still green the batch we sent
									$.each(batch, function(_, value){
										cms_update_mark_file(value.fn_hash, value.letter, 'green')
									})
								}

								batches_active = batches_active - 1

								if (batches_active == 0 && !cms_update_pending_left()){
									cms_update_finish_transfer()
								} else {
									cms_update_pump_batches()
								}

							}
						})

					}

					if (batches_active == 0 && !cms_update_pending_left()){
						cms_update_finish_transfer()
					}

				}

				cms_update_pump_batches()

			}
		})

	})

}

function cms_update_init($root){

	// Bind once on document for installed + available tables
	if ($('body').hasClass('cms_update_js_ok')){
		return
	}
	$('body').addClass('cms_update_js_ok')

	$(document).on('click.cms', '.cms_update_button', function(){

		var area = $(this).data('area')
		if (area === undefined){
			area = ''
		}
		cms_update_run_pipeline(area)

	})

	$(document).on('click.cms', '.cms_update_install_button', function(){

		var area = $(this).data('area')
		if (!area){
			return
		}

		cms_update_run_pipeline(area, {
			done_message: 'Module installed',
			after_cleanup: function($popup, state){

				get_ajax('cms/cms_update', {
					'do': 'cms_update_enable',
					'area': area,
					'success': function(data){

						if (data.result && data.result.result && data.result.result.error){
							cms_update_popup_finish($popup, state)
							cms_notification('Installed files but failed to enable module: ' + data.result.result.error, 8)
							return
						}

						cms_notification('Module installed. Reload if schema tables are needed.', 6)
						cms_update_confirm_area(area, $popup, state, {
							'reload': true
						})

					}
				})

			}
		})

	})

	$(document).on('click.cms', '.cms_update_release_button', function(){

		var area = $(this).data('area')
		if (area === undefined){
			area = ''
		}

		var label = $(this).closest('.cms_update_row').find('.cms_update_cell').first().text() || 'module'

		get_ajax_panel('cms/cms_popup_yes_no', {
			'text': 'Release ' + label + ' from the live tree into cache/master?<br>Clients will receive this snapshot only.',
		}, function(data){
			panels_display_popup(data.result._html, {
				'yes': function(){

					get_ajax('cms/cms_update', {
						'do': 'cms_update_release',
						'area': area,
						'success': function(resp){

							var result = (resp.result && resp.result.result) ? resp.result.result : (resp.result || {})

							if (result.error){
								cms_notification('Release failed: ' + result.error, 8)
								return
							}

							if (result.row_html){
								cms_update_apply_row_html(area, result.row_html)
							}

							cms_notification(result.message || ('Released ' + (result.version || '')), 5)

						}
					})

				}
			})
		})

	})

}

function cms_update_resize(){

}

function cms_update_destroy($root){

	$(document).off('click.cms', '.cms_update_button')
	$(document).off('click.cms', '.cms_update_install_button')
	$(document).off('click.cms', '.cms_update_release_button')
	$('body').removeClass('cms_update_js_ok')

}

$(document).ready(function(){

	$(window).on('resize.cms', function(){
		cms_update_resize()
	})

	cms_update_init()

	cms_update_resize()

})
