function cms_page_panel_import_bind($popup){

	cms_popup_bind_cancel($popup)

	$popup.find('.cms_page_panel_import_input').off('change.cms_import').on('change.cms_import', function(){

		var form = $popup.find('.cms_page_panel_import_form').get(0)
		if (!form || !this.files || !this.files.length){
			return
		}

		var data = new FormData(form)
		data.append('panel_id', 'cms/cms_page_panel_import')
		data.append('do', 'cms_page_panel_import_read')
		data.append('list_panel', $('.cms_list_container').data('panel_name') || '')

		$popup.find('.cms_page_panel_import_error').remove()

		$.ajax({
			url: _cms_base + 'ajax_api/get_panel',
			type: 'POST',
			data: data,
			processData: false,
			contentType: false,
			dataType: 'json',
			success: function(data){
				if (!data || !data.result || !data.result._html){
					cms_page_panel_import_show_upload_error($popup, data && data.result && data.result.message)
					return
				}
				$popup.find('.cms_popup_content').html(data.result._html)
				cms_page_panel_import_bind($popup)
			},
			error: function(){
				cms_page_panel_import_show_upload_error($popup, '')
			}
		})

	})

	$popup.find('.cms_page_panel_import_tick').off('click.cms_import').on('click.cms_import', function(e){
		e.preventDefault()
		e.stopPropagation()
		var on = $(this).text().indexOf('v') === -1
		$(this).text(on ? '[v]' : '[ ]')
		$(this).toggleClass('cms_page_panel_import_tick_on', on)
	})

	$popup.find('.cms_page_panel_import_go').off('click.cms_import').on('click.cms_import', function(e){
		e.preventDefault()
		e.stopPropagation()

		var $block = $(this).closest('.cms_page_panel_import_block')
		if ($block.hasClass('cms_page_panel_import_busy')){
			return
		}
		$block.addClass('cms_page_panel_import_busy')
		$block.children('.cms_page_panel_import_error').remove()

		var fk_ids = []
		$block.find('.cms_page_panel_import_tick_on').each(function(){
			var id = parseInt($(this).data('fk_id'), 10)
			if (id > 0){
				fk_ids.push(id)
			}
		})

		get_ajax('cms/cms_page_panel_import', {
			'do': 'cms_page_panel_import_apply',
			'token': $popup.find('.cms_page_panel_import_body').attr('data-token'),
			'zip_id': $block.data('zip_id'),
			'mode': $(this).data('mode'),
			'fk_ids': JSON.stringify(fk_ids),
			'no_html': '1',
			'error': function(){
				cms_page_panel_import_show_error($block, '')
			},
		}).then(function(data){

			if (!data.result || !data.result.ok){
				cms_page_panel_import_show_error($block, data.result && data.result.message)
				return
			}

			$block.remove()
			cms_list_load($('.cms_list_container').data('start'), $('.cms_list_container').data('limit'))

			if (!$popup.find('.cms_page_panel_import_block').length){
				cms_page_panel_import_discard($popup)
				$popup.find('.cms_page_panel_import_list').html('<div class="cms_page_panel_import_done">All imported</div><div class="cms_page_panel_import_ok">OK</div>')
				cms_page_panel_import_bind($popup)
			}

		})

	})

	$popup.find('.cms_page_panel_import_ok').off('click.cms_import').on('click.cms_import', function(e){
		e.preventDefault()
		cms_popup_close($popup)
	})

}

function cms_page_panel_import_discard($popup){

	if (!$popup || !$popup.length){
		return
	}

	var token = $popup.find('.cms_page_panel_import_body').attr('data-token') || ''
	if (!token){
		return
	}

	$popup.find('.cms_page_panel_import_body').attr('data-token', '')
	get_ajax('cms/cms_page_panel_import', {
		'do': 'cms_page_panel_import_discard',
		'token': token,
		'no_html': '1',
	})

}

function cms_page_panel_import_show_upload_error($popup, message){

	var text = message || 'The import could not be completed.'
	var $body = $popup.find('.cms_page_panel_import_body')
	if (!$body.length){
		$popup.find('.cms_popup_content').html('<div class="cms_page_panel_import_body"><div class="cms_page_panel_import_error"></div></div>')
		$popup.find('.cms_page_panel_import_error').text(text)
		return
	}

	$body.children('.cms_page_panel_import_error').remove()
	$('<div class="cms_page_panel_import_error"></div>').text(text).appendTo($body)

}

function cms_page_panel_import_show_error($block, message){

	$block.removeClass('cms_page_panel_import_busy')
	$block.children('.cms_page_panel_import_error').remove()
	var text = message || 'The import could not be completed.'
	var $error = $('<div class="cms_page_panel_import_error"></div>').text(text)
	$block.find('.cms_page_panel_import_actions').after($error)

}

function cms_page_panel_import_open(){

	cms_popup_open_ajax('import', function($popup){

		$popup.find('.cms_popup_content').html('Loading...')

		get_ajax_panel('cms/cms_page_panel_import', {
			'do': 'cms_page_panel_import_settings',
		}, function(data){

			$popup.find('.cms_popup_content').html(data.result._html)
			cms_page_panel_import_bind($popup)

		})

	})

}
