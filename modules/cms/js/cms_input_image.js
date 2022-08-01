function cms_input_image_init(){
	
	var $cms_input_image_containers = $('.cms_input_image_container');
	
	$cms_input_image_containers.each(function(){
		
		var $cms_input_image_container = $(this);
		
		if (!$cms_input_image_container.data('cms_initiated')){

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
			
			$cms_input_image_container.data('cms_initiated', true);

		}
		
	})

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
							.css({'background-image': 'url(' + config_url + 'img/' + $('.cms_input_image_input', $container).val()})
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
						.css({'background-image': 'url(' + config_url + 'img/' + $('.cms_input_image_input', $container).val()})
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

	get_ajax_panel('cms/cms_images', {'filename': original_filename, 'category': params.category}, function(data){
		panels_display_popup(data.result._html, {
			'select': function(after){
				// just before closing (select), check, if selected value is still a valid image
				$(document).off('keyup.cms');
				get_ajax_panel('cms/cms_images_operations', {
					'filename': $('.popup_select').data('value'),
					'do': 'cms_images_check_by_filename'
				}, function(data){

					// select process from here
					$(params.input_selector).val(path + data.result.filename);

					if (typeof params.container_selector != 'undefined'){
						if (data.result.filename == ''){
							$(params.container_selector).html('-- no image --');
						} else {
							$(params.container_selector).html(data.result._html);
						}
					}

					// update meta fields
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

					params.after({'name':data.result.filename});
					after();
					
				});
			},
			'cancel': function(after){
				// check if currently in input image still ok?
				$(document).off('keyup.cms');
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
					after();
				});					
			}
		}); 
	});

}

$(document).ready(function() {
	
	cms_input_image_init();
	
});
