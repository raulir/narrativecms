var cms_edit_slug_check_timer = null
var cms_edit_slug_check_request_id = 0

function cms_edit_slug_set_status($container, status, message){

	var $status = $container.find('.cms_edit_slug_status')

	$status.removeClass('cms_edit_slug_status_success cms_edit_slug_status_error')

	if (!message){
		$status.text('')
		return
	}

	if (status === 'success'){
		$status.addClass('cms_edit_slug_status_success')
	} else if (status === 'error'){
		$status.addClass('cms_edit_slug_status_error')
	}

	$status.text(message)

}

function cms_edit_slug_check($container){

	var cms_page_panel_id = $container.data('cms_page_panel_id')
	var current_slug = $container.data('current_slug')
	var new_slug = $container.find('.cms_edit_slug_input').val()

	if (!String(new_slug).trim()){
		cms_edit_slug_set_status($container, '', '')
		$container.data('cms_edit_slug_ok', 0)
		return
	}

	var request_id = ++cms_edit_slug_check_request_id

	get_ajax('cms/cms_edit_slug', {
		'do': 'check_slug',
		'cms_page_panel_id': cms_page_panel_id,
		'new_slug': new_slug,
	}).then(function(data){

		if (request_id !== cms_edit_slug_check_request_id){
			return
		}

		var result = data.result || {}

		cms_edit_slug_set_status($container, result.check_status, result.check_message)
		$container.data('cms_edit_slug_ok', result.check_status === 'success' ? 1 : 0)
		$container.data('cms_edit_slug_has_checked', 1)

	})

}

function cms_edit_slug_schedule_check($container){

	if (cms_edit_slug_check_timer){
		clearTimeout(cms_edit_slug_check_timer)
	}

	var delay = $container.data('cms_edit_slug_has_checked') ? 500 : 0

	cms_edit_slug_check_timer = setTimeout(function(){
		cms_edit_slug_check($container)
	}, delay)

}

function cms_edit_slug_bind($popup){

	var $container = $popup.find('.cms_edit_slug_container')

	if (!$container.length){
		return
	}

	$container.find('.cms_edit_slug_input').off('input.cms').on('input.cms', function(){
		cms_edit_slug_schedule_check($container)
	})

	$container.find('.cms_edit_slug_update').off('click.cms').on('click.cms', function(){

		var cms_page_panel_id = $container.data('cms_page_panel_id')
		var new_slug = $container.find('.cms_edit_slug_input').val()

		get_ajax('cms/cms_edit_slug', {
			'do': 'update_slug',
			'cms_page_panel_id': cms_page_panel_id,
			'new_slug': new_slug,
		}).then(function(data){

			var result = data.result || {}

			if (result.error){
				cms_edit_slug_set_status($container, 'error', result.error)
				return
			}

			if (result.slug){
				$container.data('current_slug', result.slug)
				$container.find('.cms_edit_slug_current').text(result.slug)
				$container.find('.cms_edit_slug_input').val(result.slug)
				cms_edit_slug_set_status($container, 'success', 'Slug updated')
				$container.data('cms_edit_slug_ok', 1)
			}

		})

	})

	cms_edit_slug_schedule_check($container)

}

function cms_page_panel_button_edit_slug_init(){

	$('.cms_page_panel_edit_slug').off('click.cms').on('click.cms', function(e){

		e.preventDefault()
		e.stopPropagation()

		var cms_page_panel_id = $(this).data('cms_page_panel_id')

		cms_popup_open_ajax('edit_slug', function($popup){

			$popup.find('.cms_popup_content').html('Loading...')

			get_ajax_panel('cms/cms_edit_slug', {
				'cms_page_panel_id': cms_page_panel_id,
				'do': 'form',
			}, function(data){

				$popup.find('.cms_popup_content').html(data.result._html)
				cms_popup_bind_cancel($popup)
				cms_edit_slug_bind($popup)

			})

		})

	})

}

function cms_page_panel_button_edit_slug_resize(){

}

function cms_page_panel_button_edit_slug_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', cms_page_panel_button_edit_slug_resize)
	$(window).on('scroll.cms', cms_page_panel_button_edit_slug_scroll)

	cms_page_panel_button_edit_slug_init()
	cms_page_panel_button_edit_slug_resize()
	cms_page_panel_button_edit_slug_scroll()

})