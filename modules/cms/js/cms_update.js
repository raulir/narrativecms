function cms_update_run_pipeline(area, options){

	options = options || {}
	var after_cleanup = options.after_cleanup || null
	var done_message = options.done_message || 'CMS updated'

	$('.cms_update_result').html('Getting list of files ...')

	get_ajax('cms/cms_update', {
		'do': 'cms_update_list',
		'area': area,
		'success': function(data){

			var files = (data.result && data.result.result) ? data.result.result : []
			if (!files.length){
				$('.cms_update_result').html('No files to transfer.')
				if (after_cleanup){
					after_cleanup()
				}
				return
			}

			// print out list of files
			$('.cms_update_result').html('')
			$.each(files, function(index, value){
				$('.cms_update_result').html($('.cms_update_result').html() + '<div class="cms_update_result_file_' + value.fn_hash + '">' +
						'<span class="cms_update_tick">(<span style="font-weight: bold; color: #d0d0d0; ">' + value.letter + '</span>)</span>&nbsp;' +
						value.filename + '</div>')
				files[index].updated = 0
			})

			var number_updating = 0

			// update
			var interval = setInterval(function(){

				$.each(files, function(key, value){

					if (value.updated == 0 && number_updating < 7){
						files[key].updated = 1
						number_updating = number_updating + 1
						$('.cms_update_result_file_' + value.fn_hash).children('.cms_update_tick').html('(<span style="color: orange; font-weight: bold; ">' + value.letter + '</span>)')

						get_ajax('cms/cms_update', {
							'do': 'cms_update_file',
							'area': area,
							'filename': value.filename,
							'letter': value.letter,
							'success': function(data){
								$('.cms_update_result_file_' + data.result.result.fn_hash).children('.cms_update_tick')
										.html('(<span style="color: green; font-weight: bold; ">' + data.result.result.letter + '</span>)')
								number_updating = number_updating - 1
							}
						})
					}

				})

				if (number_updating == 0){

					clearInterval(interval)

					// copy over
					get_ajax('cms/cms_update', {
						'do': 'cms_update_copy',
						'area': area,
						'success': function(data){

							get_ajax('cms/cms_update', {
								'do': 'cms_update_cleanup',
								'area': area,
								'success': function(data){

									if (after_cleanup){
										after_cleanup()
									} else {
										cms_notification(done_message, 5)
									}

								}
							})

						}
					})

				}

			}, 300)

		}
	})

}

function cms_update_init($root){

	var $scope = $root ? $root.find('.cms_update_table') : $('.cms_update_table')

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
			after_cleanup: function(){

				get_ajax('cms/cms_update', {
					'do': 'cms_update_enable',
					'area': area,
					'success': function(data){

						if (data.result && data.result.result && data.result.result.error){
							cms_notification('Installed files but failed to enable module: ' + data.result.result.error, 8)
							return
						}

						cms_notification('Module installed. Reload if schema tables are needed.', 6)
						window.location.reload()

					}
				})

			}
		})

	})

}

function cms_update_resize(){

}

function cms_update_destroy($root){

	$(document).off('click.cms', '.cms_update_button')
	$(document).off('click.cms', '.cms_update_install_button')
	$('body').removeClass('cms_update_js_ok')

}

$(document).ready(function(){

	$(window).on('resize.cms', function(){
		cms_update_resize()
	})

	cms_update_init()

	cms_update_resize()

})
