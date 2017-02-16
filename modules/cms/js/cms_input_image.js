function cms_input_image_rename(old_name){
	
	// TODO: check if such name exists on page
	
	var new_name = ('0000'+Math.random().toString(36).replace('.', '')).substr(-5);
	
	$('.cms_input_image_container_' + old_name + ' label').attr({'for':'cms_input_image_' + new_name});
	$('.cms_input_image_container_' + old_name + ' .admin_image_content')
			.removeClass('cms_input_image_content_' + old_name).addClass('cms_input_image_content_' + new_name);
	$('.cms_input_image_container_' + old_name + ' .admin_input_button').data('name', new_name);
	$('.cms_input_image_container_' + old_name + ' input').removeClass('cms_image_input_' + old_name).addClass('cms_image_input_' + new_name);
	
	$('.cms_input_image_container_' + old_name).removeClass('cms_input_image_container_' + old_name).addClass('cms_input_image_container_' + new_name);
	
}

function cms_input_image_popup($element){
	
	var input_name = $element.data('name');
	cms_input_image_load_images({
		'input_selector': '.cms_image_input_' + input_name, 
		'container_selector': '.cms_input_image_content_' + input_name,
		'category': $element.data('category')
	});
	
}

function cms_input_image_clear($element){
	
	var input_name = $element.data('name');
	$('.cms_image_input_' + input_name + '').val('');
	$('.cms_input_image_content_' + input_name).html('-- no image --');
	
}

function cms_input_image_init(){

	// columns are created only once and all such inputs are in these
	$('.admin_column .cms_input_image_button').on('click.r', function(event){
		cms_input_image_popup($(this));
	});
	$('.admin_column .cms_input_image_clear').off('click.r').on('click.r', function(event){
		cms_input_image_clear($(this));
	});
	
	// init repeater image inputs
	$('.admin_repeater_container').on('click.r', '.cms_input_image_button', function(event){
		cms_input_image_popup($(this));
	});
	$('.admin_repeater_container').on('click.r', '.cms_input_image_clear', function(event){
		cms_input_image_clear($(this));
	});

}

function cms_input_image_load_images(params){
	
	params = $.extend(true, {
		'after': function(){},
		'path_type': 'img', // could be 'root'
		'category': ''
	}, params);
	
	var original_filename = $(params.input_selector).val();

	var path = '';
	// as cms image system works on img/ path ...
	if (params.path_type == 'root'){
		path = config_url + 'img/';
		original_filename = original_filename.replace(path, '');
	}

	get_ajax_panel('cms_images', {'filename': original_filename, 'category': params.category}, function(data){
		panels_display_popup(data.result.html, {
			'select': function(after){
				// just before closing (select), check, if selected value is still a valid image
				$(document).off('keyup.cms');
				get_ajax_panel('cms_images_operations', {
					'filename': $('.popup_select').data('value'),
					'do': 'cms_images_check_by_filename'
				}, function(data){

					// select process from here
					$(params.input_selector).val(path + data.result.filename);
					
					if (typeof params.container_selector != 'undefined'){
						if (data.result.filename == ''){
							$(params.container_selector).html('-- no image --');
						} else {
							$(params.container_selector).html(data.result.html);
						}
					}
					
					// update meta fields
					$container = $(params.container_selector).closest('.cms_input_image');
					$container.siblings('.admin_input').each(function(){
						$('.cms_meta', this).each(function(){
							var $this = $(this);
							if ($this.data('meta_src') == $container.data('name')){
								if ($this.val() == '' && data.result[$this.data('meta_field')]){
									$this.val(data.result[$this.data('meta_field')]);
								}
							}
						});
					});
					
					
					$(params.container_selector).data('name')
				
					params.after();
					after();
					
				});
			},
			'cancel': function(after){
				// check if currently in input image still ok?
				$(document).off('keyup.cms');
				get_ajax_panel('cms_images_operations', {
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
