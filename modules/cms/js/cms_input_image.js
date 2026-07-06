function cms_input_image_init($root){

	var $cms_input_image_containers = $root ? $root.find('.cms_input_image_container') : $('.cms_input_image_container');

	$cms_input_image_containers.not('.cms_input_image_ok').each(function(){

		var $cms_input_image_container = $(this);

		$cms_input_image_container.addClass('cms_input_image_ok');

		$('.cms_input_image_button', $cms_input_image_container).on('click.cms', function(){
			cms_input_image_popup($(this));
		});
		$('.cms_input_image_clear', $cms_input_image_container).on('click.cms', function(){
			cms_input_image_clear($(this));
		});

		$('.cms_input_image_size_small .cms_input_image_overlay', $cms_input_image_container)
			.on('mouseenter.cms', function(){
				$(this).closest('.cms_input_image_size_small').addClass('cms_input_image_hover');
			})
			.on('mouseleave.cms', function(){
				$(this).closest('.cms_input_image_size_small').removeClass('cms_input_image_hover');
			});

	});

}

function cms_input_image_destroy($root){

	var $cms_input_image_containers = $root ? $root.find('.cms_input_image_container') : $('.cms_input_image_container');

	$cms_input_image_containers.filter('.cms_input_image_ok').each(function(){

		var $cms_input_image_container = $(this);

		$cms_input_image_container.removeClass('cms_input_image_ok');
		$cms_input_image_container.off('.cms');

	});

}

function cms_input_image_rename(old_name){
	
	// TODO: check if such name exists on page
	
	var new_name = ('0000'+Math.random().toString(36).replace('.', '')).substr(-5);
	
	$('.cms_input_image_area_' + old_name + ' label').attr({'for':'cms_input_image_' + new_name});
	$('.cms_input_image_area_' + old_name + ' .cms_input_image_content')
			.removeClass('cms_input_image_content_' + old_name).addClass('cms_input_image_content_' + new_name);
	$('.cms_input_image_area_' + old_name + ' .cms_input_button').data('name', new_name);
	$('.cms_input_image_area_' + old_name + ' input').removeClass('cms_image_input_' + old_name).addClass('cms_image_input_' + new_name);
	
	$('.cms_input_image_area_' + old_name).removeClass('cms_input_image_area_' + old_name).addClass('cms_input_image_area_' + new_name);
	
}

function cms_input_image_popup($element){

	var input_name = $element.data('name');
	cms_input_image_load_images({
		'input_selector': '.cms_image_input_' + input_name, 
		'container_selector': '.cms_input_image_content_' + input_name,
		'category': $element.data('category'),
		'after': function(){
			// update xy inputs
			if ($('.cms_input_xy_target_' + input_name).length){
				
				var $container = $('.cms_input_image_content_' + input_name).closest('.cms_input_image');
				
				if (!$('.cms_input_image_input', $container).val()){
					
					$('.cms_input_xy_image_inner', $('.cms_input_xy_target_' + input_name)).addClass('cms_input_xy_empty')
						.css({'background-image':'', 'height': '', 'width': ''}).html('-- empty target --')
					$('.cms_input_xy_target_' + input_name).data('target_image', '')
					
				} else {
					
					$('.cms_input_xy_image_inner', $('.cms_input_xy_target_' + input_name)).removeClass('cms_input_xy_empty')
						.html('<div class="cms_input_xy_pointer"></div>')

					$('.cms_input_xy_image_inner', $('.cms_input_xy_target_' + input_name)).data('w', $container.data('w'))
					$('.cms_input_xy_image_inner', $('.cms_input_xy_target_' + input_name)).data('h', $container.data('h'))
					$('.cms_input_xy_image_inner', $('.cms_input_xy_target_' + input_name))
							.css({'background-image': 'url(' + _cms_base + 'img/' + $('.cms_input_image_input', $container).val()})
					$('.cms_input_xy_target_' + input_name).data('target_image', $('.cms_input_image_input', $container).val())
				
				}
				
				cms_input_xy_init()
				
			}
			// update mask inputs
			if ($('.cms_input_mask_target_' + input_name).length){
				
				if ($('.cms_input_mask_image_inner', $('.cms_input_mask_target_' + input_name)).length == 0){
					$('.cms_input_mask_image', $('.cms_input_mask_target_' + input_name)).append('<div class="cms_input_mask_image_inner"></div>')
				}
				
				var $container = $('.cms_input_image_content_' + input_name).closest('.cms_input_image');
				
				$('.cms_input_mask_image_inner', $('.cms_input_mask_target_' + input_name)).data('w', $container.data('w'))
				$('.cms_input_mask_image_inner', $('.cms_input_mask_target_' + input_name)).data('h', $container.data('h'))
				$('.cms_input_mask_image_inner', $('.cms_input_mask_target_' + input_name))
						.css({'background-image': 'url(' + _cms_base + 'img/' + $('.cms_input_image_input', $container).val()})
				$('.cms_input_mask_target_' + input_name).data('target_image', $('.cms_input_image_input', $container).val())
				
				cms_input_mask_init()
				
			}
		}
	});
	
}

function cms_input_image_clear($element){
	
	var input_name = $element.data('name');
	$('.cms_image_input_' + input_name + '').val('');
	$('.cms_input_image_content_' + input_name).html('-- no image --');
	
	// clear xy input
	
	if ($('.cms_input_xy_target_' + input_name).length){
		
		var $container = $('.cms_input_image_content_' + input_name).closest('.cms_input_image');

		$('.cms_input_xy_image_inner', $('.cms_input_xy_target_' + input_name)).addClass('cms_input_xy_empty')
			.css({'background-image': '', 'height': '', 'width': ''}).html('-- empty target --')
		$('.cms_input_xy_target_' + input_name).data('target_image', '')
	
	}
	
}

function cms_input_image_apply_selection(params, filename, path, callback){

	get_ajax_panel('cms/cms_images_operations', {
		'filename': filename,
		'do': 'cms_images_check_by_filename'
	}, function(data){

		$(params.input_selector).val(path + data.result.filename);

		if (typeof params.container_selector != 'undefined'){
			if (data.result.filename == ''){
				$(params.container_selector).html('-- no image --');
			} else {
				$(params.container_selector).html(data.result._html);
			}
			if (typeof cms_video_init_when_ready === 'function'){
				cms_video_init_when_ready($(params.container_selector))
			} else if (typeof cms_video_init === 'function'){
				cms_video_init()
			}
		}

		var $container = $(params.container_selector).closest('.cms_input_image');
		$container.siblings('.cms_input').each(function(){
			$('.cms_meta', this).each(function(){
				var $this = $(this);
				if ($this.data('meta_src') == $container.data('name')){
					if ($this.val() == '' && data.result[$this.data('meta_field')]){
						$this.val(data.result[$this.data('meta_field')]);
					}
				}
			});
		});

		$container.data('h', data.result.original_height)
		$container.data('w', data.result.original_width)

		$(params.container_selector).data('name');

		if (typeof callback === 'function'){
			callback(data);
		}

	});

}

function cms_input_image_resume_preview_videos(params){

	if (!params || !params.container_selector){
		return
	}

	if (typeof cms_video_init_when_ready === 'function'){
		cms_video_init_when_ready($(params.container_selector))
	} else if (typeof cms_video_resume_all === 'function'){
		cms_video_resume_all($(params.container_selector))
	}

}

function cms_input_image_load_images(params){
	
	params = $.extend(true, {
		'path_type': 'img', // could be 'root'
		'category': ''
	}, params);
	
	var original_filename = $(params.input_selector).val();

	var path = '';
	// as cms image system works on img/ path ...
	if (params.path_type == 'root'){
		path = 'img/';
		original_filename = original_filename.replace(path, '');
	}

	get_ajax_panel('cms/cms_images', {
		'filename': original_filename,
		'category': params.category,
		'_no_js': '1',
	}, function(data){
		panels_display_popup(data.result._html, {
			'select': function(after){
				$(document).off('keyup.cms');
				cms_input_image_apply_selection(params, $('.popup_select').data('value'), path, function(data){
					params.after({'name': data.result.filename});
					if (typeof cms_page_panel_schedule_title_preview === 'function'){
						cms_page_panel_schedule_title_preview()
					}
					after();
				});
			},
			'cancel': function(after){
				$(document).off('keyup.cms');

				var edited_filename = $('.cms_images_area').data('edited_filename') || ''
				var edited_from_filename = $('.cms_images_area').data('edited_from_filename') || ''
				var refresh_filename = ''
				if (edited_filename && edited_filename === original_filename){
					refresh_filename = edited_filename
				} else if (edited_filename && edited_from_filename === original_filename){
					refresh_filename = edited_filename
				}

				if (refresh_filename){
					cms_input_image_apply_selection(params, refresh_filename, path, function(data){
						params.after({'name': data.result.filename});
						after();
					});
					return;
				}

				get_ajax_panel('cms/cms_images_operations', {
					'filename': original_filename,
					'do': 'cms_images_check_by_filename'
				}, function(data){
					if (data.result.filename == ''){
						$(params.input_selector).val('');
						if (typeof params.container_selector != 'undefined'){
							$(params.container_selector).html('-- no image --');
						}
					}
					params.after();
					cms_input_image_resume_preview_videos(params);
					after();
				});
			}
		});
		var $popup = $('.popup_container.cms_images_container').last()

		$popup.find('.cms_images_area').removeData('edited_filename')
		$popup.find('.cms_images_area').removeData('edited_from_filename')

		if (typeof cms_images_popup_init === 'function'){
			cms_images_popup_init($popup)
		}

	});

}

$(document).ready(function() {
	
	cms_input_image_init();
	
});
