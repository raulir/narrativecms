function cms_update_popup_files($popup){

	return $popup.find('.cms_update_popup_files')

}

function cms_update_popup_overlay_show($popup, label){

	var $overlay = $popup.find('.cms_update_popup_overlay')
	if (label){
		$popup.find('.cms_update_popup_overlay_label').text(label)
	}
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

/**
 * Update area → schema package id (core package is empty area → cms).
 */
function cms_update_area_to_schema_module(area){

	if (area === undefined || area === null || area === ''){
		return 'cms'
	}

	return String(area)

}

/**
 * Load single-module schema fragment into the popup (same scroll stream as file ticks).
 * Uses schema_module — not "module" (panel loader overwrites module with package name "cms").
 */
function cms_update_load_schema(area, $popup, state, options){

	options = options || {}

	var module = cms_update_area_to_schema_module(area)
	var $body = $popup.find('.cms_update_popup_schema_body')

	cms_update_popup_overlay_show($popup, 'Checking schema...')
	$body.html('<div class="cms_update_popup_schema_pending">Checking schema for ' + module + '…</div>')

	get_ajax_panel('cms/cms_schema', {
		'schema_module': module,
		'filter_module': module,
		'fragment': '1'
	}, function(result){

		var res = result && result.result ? result.result : {}

		if (res._html){
			$body.html(res._html)
			if (typeof cms_schema_init === 'function'){
				cms_schema_init($body)
			}
		} else {
			$body.html(
				'<div class="cms_update_popup_schema_none">' +
					'No schema updates available for this module' +
				'</div>'
			)
		}

		// Scroll shared body so schema is visible after ticks
		var $scroll = $popup.find('.cms_update_popup_body')
		var $schema = $popup.find('.cms_update_popup_schema')
		if ($scroll.length && $schema.length){
			var delta = $schema.offset().top - $scroll.offset().top
			$scroll.animate({ scrollTop: $scroll.scrollTop() + delta - 8 }, 200)
		}

		if (options.after_schema){
			options.after_schema(res)
		}

		// No full page reload — installed/available tables already updated via row_html
		cms_update_popup_finish($popup, state)

	})

}

/**
 * Core package uses area '' — match only rows that have data-area (never the header row).
 * Use attr(), not .data(): empty data-area="" must stay '' and not look like a missing attr.
 */
function cms_update_row_area_matches($row, area){

	if (!$row.is('[data-area]')){
		return false
	}

	return String($row.attr('data-area') || '') === String(area || '')

}

function cms_update_apply_row_html(area, row_html){

	var $table = $('.cms_update_table_installed')
	if (!$table.length){
		$table = $('.cms_update_table').not('.cms_update_table_available').first()
	}

	var $existing = $table.find('.cms_update_row[data-area]').filter(function(){
		return cms_update_row_area_matches($(this), area)
	})

	if ($existing.length){
		$existing.first().replaceWith(row_html)
		$existing.slice(1).remove()
	} else {
		$table.append(row_html)
	}

	cms_update_remove_available_row(area)

}

function cms_update_remove_available_row(area){

	var $available = $('.cms_update_table_available')
	if (!$available.length){
		return
	}

	$available.find('.cms_update_row[data-area]').filter(function(){
		return cms_update_row_area_matches($(this), area)
	}).remove()

	if ($available.find('.cms_update_row[data-area]').length === 0){
		$available.prev('.cms_update_section_label').remove()
		$available.remove()
	}

}

/**
 * Ensure “Available to install” table exists; append server-rendered install row.
 */
function cms_update_append_available_html(html){

	if (!html){
		return
	}

	var $available = $('.cms_update_table_available')
	if (!$available.length){
		var $wrap = $('.cms_update_table_installed').parent()
		if (!$wrap.length){
			$wrap = $('.cms_update_table').first().parent()
		}
		$wrap.append(
			'<div class="cms_update_section_label">Available to install</div>' +
			'<div class="cms_update_table cms_update_table_available">' +
				'<div class="cms_update_row">' +
					'<div class="cms_update_head">Module</div>' +
					'<div class="cms_update_head">Local</div>' +
					'<div class="cms_update_head">Master</div>' +
					'<div class="cms_update_head cms_update_cell_right"></div>' +
				'</div>' +
			'</div>'
		)
		$available = $('.cms_update_table_available')
	}

	$available.append(html)

}

function cms_update_remove_installed_row(area){

	var $table = $('.cms_update_table_installed')
	if (!$table.length){
		return
	}

	$table.find('.cms_update_row[data-area]').filter(function(){
		return cms_update_row_area_matches($(this), area)
	}).remove()

}

function cms_update_confirm_area(area, $popup, state, options){

	options = options || {}

	cms_update_popup_overlay_show($popup, 'Confirming module...')

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

			cms_update_load_schema(area, $popup, state, options)

		}
	})

}

/**
 * Failure line plus one line per file (details: filename => reason). Text only.
 */
function cms_update_show_failure($popup, message, details){

	var $files = cms_update_popup_files($popup)
	$files.append($('<div class="cms_update_popup_failed"></div>').text(message))

	if (details && typeof details === 'object'){
		$.each(details, function(filename, reason){
			$files.append($('<div class="cms_update_popup_failed_file"></div>').text(filename + ' — ' + reason))
		})
	}

}

/**
 * Before the apply step (file list or download failed): the live tree was not touched by
 * this run. Discard the staged download and show why.
 */
function cms_update_fail($popup, state, area, message, details){

	cms_update_show_failure($popup, message, details)

	var finish = function(){
		cms_update_popup_finish($popup, state)
		cms_notification(message, 8)
	}

	get_ajax('cms/cms_update', {
		'do': 'cms_update_discard',
		'area': area,
		'success': finish,
		'error': finish
	}).catch(function(){})

}

/**
 * Update would overwrite or delete local edits: show the warning with Continue / Cancel.
 * Cancel discards the (empty) update; nothing was downloaded or changed yet.
 */
function cms_update_ask_overwrite($popup, state, area, local_edits, on_continue){

	var text = local_edits
			? local_edits + ' file(s) listed below have local edits that this update will overwrite or delete.'
			: 'This package has local changes, but there is no per-file record of the installed version, '
				+ 'so the edited files cannot be named. The update may overwrite them.'

	var $ask = $('<div class="cms_update_popup_ask"></div>')
	$ask.append($('<div class="cms_update_popup_ask_text"></div>').text(text + ' Continue?'))

	var $continue = $('<div class="cms_tool_button cms_update_popup_ask_continue">Continue update</div>')
	var $cancel = $('<div class="cms_tool_button cms_update_popup_ask_cancel">Cancel</div>')
	$ask.append($continue, $cancel)

	cms_update_popup_files($popup).prepend($ask)

	$continue.on('click', function(){
		$ask.remove()
		on_continue()
	})

	$cancel.on('click', function(){
		$ask.remove()
		cms_update_fail($popup, state, area, 'Update cancelled — no files were changed')
	})

}

function cms_update_run_pipeline(area, options){

	options = options || {}
	var after_cleanup = options.after_cleanup || null
	var done_message = options.done_message || 'CMS updated'

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
					// Core update replaced this updater's own PHP/JS: load the matching script
					if (state.reload_on_close){
						window.location.reload()
					}
				}
			}
		})

		var $popup = $('.cms_update_popup_container').last()
		var $files = cms_update_popup_files($popup)

		$files.html('Getting list of files ...')

		get_ajax('cms/cms_update', {
			'do': 'cms_update_list',
			'area': area,
			'protocol': 2,
			'error': function(){
				$files.html('')
				cms_update_fail($popup, state, area, 'Could not get the list of files — no files were changed')
			},
			'success': function(data){

				var plan = (data.result && data.result.result) ? data.result.result : null

				// Empty success is only a real file list. [] or a missing plan must not confirm or enable.
				if (!plan || typeof plan !== 'object' || plan.error || !$.isArray(plan.files)){
					var reason = (plan && plan.error) ? plan.error : 'unexpected response'
					$files.html('')
					cms_update_fail($popup, state, area, 'Could not get the list of files: ' + reason + ' — no files were changed')
					return
				}

				var files = plan.files

				if (!files.length){
					$files.html('No files to transfer.')
					if (after_cleanup){
						after_cleanup($popup, state)
					} else {
						cms_notification(done_message, 5)
						cms_update_confirm_area(area, $popup, state, {})
					}
					return
				}

				$files.html('')
				$.each(files, function(index, value){
					// Filenames come from the master list: text only, never HTML
					var fn_hash = String(value.fn_hash || '').replace(/[^a-f0-9]/g, '')
					var $tick = $('<span class="cms_update_tick"></span>')
					$tick.append('(', $('<span style="font-weight: bold; color: #d0d0d0; "></span>').text(value.letter), ')')
					var $file_row = $('<div class="cms_update_result_file cms_update_result_file_' + fn_hash + '"></div>')
							.append($tick, ' ', document.createTextNode(String(value.filename)))
					if (value.local_edit){
						$file_row.addClass('cms_update_result_file_local_edit')
								.append($('<span class="cms_update_result_local_edit"></span>')
										.text(value.letter === 'D' ? ' — local edit, will be deleted' : ' — local edit, will be overwritten'))
					}
					$files.append($file_row)
					files[index].fn_hash = fn_hash
					files[index].state = 'pending'
					files[index].attempts = 0
				})

				// Files of one package belong together: one file failing after retries fails the whole update
				var batch_size = 20
				var max_batches = 2
				var max_attempts = 3 // first try + 2 retries
				var batches_active = 0
				var transfer_done = false

				function cms_update_mark_file(value, colour, note){
					var $row = $files.find('.cms_update_result_file_' + value.fn_hash)
					var $letter = $('<span style="font-weight: bold; "></span>').css('color', colour).text(value.letter)
					$row.children('.cms_update_tick').empty().append('(', $letter, ')')
					$row.children('.cms_update_result_note').remove()
					if (note){
						$row.append($('<span class="cms_update_result_note"></span>').text(' — ' + note))
					}
				}

				function cms_update_take_batch(){
					var batch = []
					$.each(files, function(key, value){
						if (value.state === 'pending' && batch.length < batch_size){
							value.state = 'active'
							value.attempts = value.attempts + 1
							batch.push(value)
						}
					})
					return batch
				}

				function cms_update_unsettled(){
					var unsettled = false
					$.each(files, function(key, value){
						if (value.state === 'pending' || value.state === 'active' || value.state === 'waiting'){
							unsettled = true
						}
					})
					return unsettled
				}

				function cms_update_settle(value, ok, reason){

					if (ok){
						value.state = 'done'
						cms_update_mark_file(value, 'green')
						return
					}

					if (value.attempts < max_attempts){
						value.state = 'waiting'
						cms_update_mark_file(value, 'orange', 'retrying (' + (reason || 'download failed') + ')')
						setTimeout(function(){
							value.state = 'pending'
							cms_update_pump_batches()
						}, 1000 * value.attempts)
						return
					}

					value.state = 'failed'
					cms_update_mark_file(value, 'red', reason || 'download failed')

				}

				function cms_update_finish_transfer(){
					if (transfer_done){
						return
					}
					transfer_done = true

					var failed = $.grep(files, function(value){
						return value.state === 'failed'
					})

					if (failed.length){
						cms_update_fail($popup, state, area,
								'Update download failed for ' + failed.length + ' file(s) after ' + (max_attempts - 1) + ' retries — no files were changed')
						return
					}

					cms_update_apply()
				}

				/**
				 * Copy the staged download into the live tree. On failure the server keeps the
				 * staged download (unless it was incomplete), so the apply can be retried here.
				 */
				function cms_update_apply_failed(message, details, can_retry){

					cms_update_show_failure($popup, message, details)
					cms_notification(message, 8)

					if (can_retry){
						var $retry = $('<div class="cms_tool_button cms_update_popup_retry">Retry apply</div>')
						$retry.on('click', function(){
							$retry.remove()
							state.allow_dismiss = false
							$popup.find('.cms_update_popup_close').hide()
							cms_update_apply()
						})
						cms_update_popup_files($popup).append($retry)
					}

					cms_update_popup_finish($popup, state)

				}

				function cms_update_apply(){

					get_ajax('cms/cms_update', {
						'do': 'cms_update_copy',
						'area': area,
						'error': function(){
							cms_update_apply_failed('Update could not be applied (request failed) — the downloaded files are kept', {}, true)
						},
						'success': function(data){

							var copied = (data.result && data.result.result) ? data.result.result : {}
							if (copied.error){
								var details = copied.failed || {}
								if (copied.missing && copied.missing.length){
									$.each(copied.missing, function(_, filename){
										details[filename] = 'not downloaded'
									})
								}
								cms_update_apply_failed(copied.error, details, !!copied.retry)
								return
							}

							// Core files changed (incl. this updater): reload when the popup closes
							if (area === ''){
								state.reload_on_close = true
								cms_update_popup_files($popup).append(
									$('<div class="cms_update_popup_reload_note"></div>')
										.text('Narrative CMS was updated — the page reloads when you close this window.')
								)
							}

							get_ajax('cms/cms_update', {
								'do': 'cms_update_cleanup',
								'area': area,
								'success': function(){

									if (after_cleanup){
										after_cleanup($popup, state)
									} else {
										cms_notification(done_message, 5)
										cms_update_confirm_area(area, $popup, state, {})
									}

								}
							}).catch(function(){})

						}
					}).catch(function(){})
				}

				function cms_update_batch_done(batch, result, request_error){

					var done = {}
					$.each((result && result.done) || [], function(_, row){
						if (row && row.fn_hash){
							done[row.fn_hash] = true
						}
					})
					var errors = (result && result.errors) || {}

					$.each(batch, function(_, value){
						cms_update_settle(value, !!done[value.fn_hash], request_error || errors[value.filename])
					})

					batches_active = batches_active - 1
					cms_update_pump_batches()

				}

				function cms_update_pump_batches(){

					while (batches_active < max_batches){

						var batch = cms_update_take_batch()
						if (!batch.length){
							break
						}

						batches_active = batches_active + 1

						var filenames = []
						$.each(batch, function(_, value){
							filenames.push(value.filename)
							cms_update_mark_file(value, 'orange')
						})

						;(function(batch){
							get_ajax('cms/cms_update', {
								'do': 'cms_update_file',
								'area': area,
								'filenames': filenames,
								'success': function(data){
									var result = (data.result && data.result.result) ? data.result.result : {}
									cms_update_batch_done(batch, result, '')
								},
								'error': function(){
									cms_update_batch_done(batch, {}, 'request failed')
								}
							}).catch(function(){})
						})(batch)

					}

					if (batches_active == 0 && !cms_update_unsettled()){
						cms_update_finish_transfer()
					}

				}

				// Local edits would be lost: ask first (in the popup, not window.confirm)
				var local_edits = parseInt(plan.local_edits, 10) || 0
				if (local_edits || plan.local_changes_unknown){
					cms_update_ask_overwrite($popup, state, area, local_edits, cms_update_pump_batches)
				} else {
					cms_update_pump_batches()
				}

			}
		}).catch(function(){})

	})

}

function cms_update_init($root){

	if ($('body').hasClass('cms_update_js_ok')){
		return
	}
	$('body').addClass('cms_update_js_ok')

	$(document).on('click.cms', '.cms_update_button', function(){

		var area = $(this).attr('data-area')
		if (area === undefined || area === null){
			area = ''
		}
		cms_update_run_pipeline(area)

	})

	$(document).on('click.cms', '.cms_update_install_button', function(){

		var area = $(this).attr('data-area')
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

						cms_notification('Module installed', 5)
						// Row moves via confirm row_html; no full page reload
						cms_update_confirm_area(area, $popup, state, {})

					}
				})

			}
		})

	})

	$(document).on('click.cms', '.cms_update_remove_button', function(){

		var area = $(this).attr('data-area')
		if (!area){
			return
		}

		var label = $(this).closest('.cms_update_row').attr('data-label') || area

		get_ajax_panel('cms/cms_popup_yes_no', {
			'text': 'Remove module <b>' + label + '</b>?<br><br>' +
				'Deletes the module files and unregisters it from site settings.<br>' +
				'<b>Database tables are kept</b> (data is not dropped).'
		}, function(data){
			panels_display_popup(data.result._html, {
				'yes': function(){

					get_ajax('cms/cms_update', {
						'do': 'cms_update_remove',
						'area': area,
						'success': function(resp){

							var result = (resp.result && resp.result.result) ? resp.result.result : (resp.result || {})

							if (result.error){
								cms_notification('Remove failed: ' + result.error, 8)
								return
							}

							cms_update_remove_installed_row(area)

							// Only if master still has a release for this package
							if (result.available && result.available_html){
								cms_update_append_available_html(result.available_html)
							}

							cms_notification('Module removed', 5)

						}
					})

				}
			})
		})

	})

	$(document).on('click.cms', '.cms_update_release_button', function(){

		var area = $(this).attr('data-area')
		if (area === undefined || area === null){
			area = ''
		}

		var label = $(this).closest('.cms_update_row').attr('data-label') || 'module'

		get_ajax_panel('cms/cms_popup_yes_no', {
			'text': 'Release ' + label + ' from the live tree into tmp/master?<br>Clients will receive this snapshot only.',
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
	$(document).off('click.cms', '.cms_update_remove_button')
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
