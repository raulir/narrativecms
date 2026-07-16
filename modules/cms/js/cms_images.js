
var cms_images_loading = false;
var cms_images_load_parameters = {};

function cms_image_open(filename){

	$('.cms_image_overlay,.cms_image_container').remove()

	get_ajax_panel('cms/cms_image', {'filename': filename}, function(data){
		$('body').append('<div class="cms_image_overlay"></div>')
		$('body').append(data.result._html)
		if (typeof cms_image_init === 'function'){
			cms_image_init($('.cms_image_container').last())
		}
		if (typeof cms_image_video_init === 'function'){
			cms_image_video_init()
		}
		if (typeof cms_image_resize === 'function'){
			cms_image_resize()
		}
		if (typeof cms_image_scroll === 'function'){
			cms_image_scroll()
		}
	})

}

function cms_images_mark() {
	$('.cms_images_mark').remove();
	$('.cms_images_selected').append('<img class="cms_images_mark" src="' + _cms_base + 'modules/cms/img/green_tick.png">');
}

function cms_images_get_selected_filename(){

	var $selected = $('.cms_images_selected')
	if ($selected.length){
		return $selected.first().data('filename') || ''
	}
	if (cms_images_load_parameters && cms_images_load_parameters.filename){
		return cms_images_load_parameters.filename
	}
	return $('.cms_images_area').data('filename') || ''

}

function cms_images_set_popup_selection(filename){

	if (!filename){
		return
	}

	$('.popup_select').data('value', filename)
	$('.cms_images_area').data('filename', filename)

}

/*
 * attaches functions when cms images panel contents are changed
 */
function cms_images_activate() {
	
	$('.cms_images_image')
		.off('click.r,mouseenter.r,mouseleave.r')
		.on('click.r', function(){
			var filename = $(this).data('filename')
			$('.cms_images_selected').removeClass('cms_images_selected');
			$(this).addClass('cms_images_selected');
			$('.popup_select').data('value', filename);
			$('.cms_images_area').data('filename', filename);
			cms_images_mark();
		})
		.on('mouseenter.r', function(){
			var $that = $(this);
			$('.cms_images_image_delete', this).on('click.r', function(e){
				e.stopPropagation();
				
				var text = 'Are you sure?';
				var $usage = $('.cms_images_image_usage', $that);
				var self_usage = parseInt($usage.data('usage_self'), 10) || 0;
				var children_usage = parseInt($usage.data('usage_children'), 10) || 0;
				var total_usage = self_usage + children_usage;
				if (total_usage > 0){
					var place_word = total_usage == 1 ? 'place' : 'places';
					text = text + '<div class="cms_images_warning">This resource is in use at ' + total_usage + ' ' + place_word + '!</div>' +
							'<div class="cms_images_warning_extra">Deleting this may cause missing images and videos in the front end.</div>';
				}
				
				get_ajax_panel('cms/cms_popup_yes_no', {'text':text}, function(data){
					panels_display_popup(data.result._html, {
						'yes': function(){
							$('.cms_images_image_cell', $that).animate({'opacity':'0'}, 100);
							get_ajax('cms/cms_images', {
								'filename': $that.data('filename'),
								'do': 'cms_images_delete_by_filename',
								'success': function(){
									var page = $('.cms_images_area').data('page');
									if ($('.cms_images_image').length == 1 && page > 0){
										page = page - 1;
									}
									cms_images_load_images(
											page,
											$('.cms_images_area').data('limit'),
											$('.cms_images_area').data('filename')
									);
								}
							})
						}
					}); 
				});
			});
			
			$('.cms_images_image_edit', this).on('click.r', function(e){
				e.stopPropagation();
				cms_image_open($that.data('filename'))
			})
			
		})
		.on('mouseleave.r', function(){
			$('.cms_images_image_delete,.cms_images_image_usage,.cms_images_image_edit', this).off('click.r');
		});
	
	cms_images_mark();
	
	$('.cms_images_search_input').focus();

}

function cms_images_upload(){
	
	var data = new FormData( $('.cms_images_new_image_form').get(0) );
	data.append('panel_id', 'cms/cms_images_upload');
	data.append('category', $('.cms_images_category').val());
	
	cms_images_transfer(data, function(data){
    	
    	$('.cms_images_search_input').val('');

    	// reload images from zero
    	cms_images_load_images('0', $('.cms_images_area').data('limit'), $('.cms_images_area').data('filename'));
		
	});

}

function cms_images_transfer(data, success){
	
	// show overlay
	var load_in_progress = true;
	var percentage = 0;
	var label = '0%';
	setTimeout(function(){
		if (load_in_progress){
			$('.cms_images_content').append('<div class="cms_images_container_upload"><div class="cms_images_container_cell"><div class="cms_images_container_bar">' +
					'<div class="cms_images_container_bg"></div><div class="cms_images_container_label">' + label + '</div></div></div></div>');
		}
	}, 300);
	
	$.ajax( {
		url: _cms_base + 'ajax_api/get_panel',
	    type: 'POST',
	    data: data,
	    processData: false,
	    contentType: false,
	    dataType: 'json',
	    success: function(data){
	    	
	    	$('.cms_images_container_upload').remove();
	    	
	    	// reinit file upload form input
	    	$('.cms_images_new_image').on('change.r', function(){
	    		$('.cms_images_new_image').off('change.r');
	    		cms_images_upload();
	    	});
	    	
	    	load_in_progress = false;

	    	success(data);

	    },
	    xhr: function() {
	        var xhr = new window.XMLHttpRequest();
	
	        xhr.upload.addEventListener('progress', function(evt) {
	        	if (evt.lengthComputable) {
	        	  
	        		var percentComplete = evt.loaded / evt.total;
	        		percentComplete = parseInt(percentComplete * 100);
	        		
        			$('.cms_images_container_bg').css({'width': percentComplete + '%'});
        			percentage = percentComplete;
	        		if (percentComplete < 99){
		        		$('.cms_images_container_label').html(percentComplete + '%');
		        		label = percentComplete + '%';
	        		} else {
		        		$('.cms_images_container_label').html('finishing');
		        		label = 'finishing';
	        		}
	
	        	}
	        }, false);
	
	        return xhr;
	    }
	});
	
}

function cms_images_load_images(page, limit, filename){
	
	cms_images_load_parameters = {
		'page': page,
		'limit': limit,
		'filename': filename,
		'category': $('.cms_images_category').val(),
		'search': $('.cms_images_search_input').val()
	}
	
	var current_page = page;
	
	if (cms_images_loading){
		return false;
	}
	
	cms_images_loading = true;
	
	$('.cms_images_paging_enabled').addClass('cms_images_paging_disabled').removeClass('cms_images_paging_enabled').off('click.cms');
	$('.cms_images_image').css({'opacity':'0.5'});
	if (typeof cms_video_cleanup == 'function'){
		cms_video_cleanup($('.cms_images_area'))
	}
	
	get_ajax_panel('cms/cms_images_page', $.extend({}, cms_images_load_parameters, {
		'_no_js': '1',
		'_no_css': '1',
	}), function(data){
		
		$('.cms_images_area').html(data.result._html).data('page', page).data('filename', filename);
		
		// update buttons etc - first
		if (parseInt(data.result.cms_images_max_page) >= 1 && parseInt(data.result.cms_images_current) > 0){
			$('.cms_images_paging_first').removeClass('cms_images_paging_disabled').addClass('cms_images_paging_enabled').data('page', '0');
		}
		// previous
		if (parseInt(data.result.cms_images_max_page) >= 1 && parseInt(data.result.cms_images_current) > 0){
			$('.cms_images_paging_previous').removeClass('cms_images_paging_disabled').addClass('cms_images_paging_enabled')
					.data('page', parseInt(data.result.cms_images_current) - 1);
		}
		// next
		if (parseInt(data.result.cms_images_max_page) >= 1 && parseInt(data.result.cms_images_current) < parseInt(data.result.cms_images_max_page)){
			$('.cms_images_paging_next').removeClass('cms_images_paging_disabled').addClass('cms_images_paging_enabled')
					.data('page', parseInt(data.result.cms_images_current) + 1);
		}
		// last
		if (parseInt(data.result.cms_images_max_page) >= 1 && parseInt(data.result.cms_images_current) < parseInt(data.result.cms_images_max_page)){
			$('.cms_images_paging_last').removeClass('cms_images_paging_disabled').addClass('cms_images_paging_enabled')
					.data('page', parseInt(data.result.cms_images_max_page));
		}
		
		$('.cms_images_paging_current').html(parseInt(data.result.cms_images_current) + 1);
		$('.cms_images_paging_total').html(parseInt(data.result.cms_images_max_page) + 1);
		
		$('.cms_images_paging_enabled').off('click.cms').on('click.cms', function(){
			cms_images_load_images($(this).data('page'), $('.cms_images_area').data('limit'), $('.cms_images_area').data('filename'));
		});
		
		// activate functionality
		cms_images_activate();

		if (typeof cms_video_init_when_ready === 'function'){
			cms_video_init_when_ready($('.cms_images_area'))
		} else if (typeof cms_video_init === 'function'){
			cms_video_init()
		}
		
		cms_images_loading = false;
		
		// check if need to reload, limit and selected filename shouldn't change so quickly 
		if (current_page != cms_images_load_parameters.page || $('.cms_images_category').val() != cms_images_load_parameters.category 
				|| $('.cms_images_search_input').val() != cms_images_load_parameters.search){
			
			cms_images_load_images(cms_images_load_parameters.page, limit, filename);
			
		}
		
	});
	
}

function cms_images_popup_destroy($popup){

	if (!$popup || !$popup.length){
		return
	}

	$popup.removeClass('cms_images_popup_ok')
	$popup.off('.cms_images_popup')
	$(document).off('keyup.cms')

}

function cms_images_popup_init($popup){

	if (!$popup || !$popup.length){
		$popup = $('.popup_container.cms_images_container').last()
	}

	if (!$popup.length){
		return
	}

	if ($popup.hasClass('cms_images_popup_ok')){
		return
	}

	$popup.addClass('cms_images_popup_ok')

	if (!$popup.parent().is('body')){
		$popup.detach().appendTo('body')
	}

	$popup.css({'display': '', 'opacity': '1'})

	$('.cms_images_upload', $popup).off('click.cms_images_popup').on('click.cms_images_popup', function(){
		$('.cms_images_new_image', $popup).click()
	})

	$('.cms_images_new_image', $popup).off('change.cms_images_popup').on('change.cms_images_popup', function(){
		$('.cms_images_new_image', $popup).off('change.cms_images_popup')
		cms_images_upload()
	})

	$('.cms_images_category', $popup).off('change.cms_images_popup').on('change.cms_images_popup', function(){
		cms_images_load_images('0', $('.cms_images_area', $popup).data('limit'), $('.cms_images_area', $popup).data('filename'))
	})

	$('.cms_images_search_input', $popup).off('keyup.cms_images_popup').on('keyup.cms_images_popup', function(e){
		if (e.which != 37 && e.which != 38 && e.which != 39){
			cms_images_load_images('0', $('.cms_images_area', $popup).data('limit'), $('.cms_images_area', $popup).data('filename'))
		}
	})

	$(document).off('keyup.cms').on('keyup.cms', function(e) {

		if (!$popup.closest('body').length){
			return true
		}

	    switch(e.which) {

	    	case 37: // left
	        	$('.cms_images_paging_previous', $popup).click()
	    		e.preventDefault()
	        	return false
	        break

	        case 38: // up
	        	$('.cms_images_search_input', $popup).val('')
	        	cms_images_load_images('0', $('.cms_images_area', $popup).data('limit'), $('.cms_images_area', $popup).data('filename'))
	    		e.preventDefault()
	        	return false
	        break

	        case 39: // right
	        	$('.cms_images_paging_next', $popup).click()
	    		e.preventDefault()
	        	return false
	        break

	        default: return true
	    }

	})

	cms_images_load_images(
		$('.cms_images_area', $popup).data('page') || 0,
		$('.cms_images_area', $popup).data('limit'),
		$('.cms_images_area', $popup).data('filename')
	)

}
