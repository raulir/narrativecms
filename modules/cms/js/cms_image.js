function cms_image_init(){

	// activate buttons
	$('.cms_image_cancel').on('click.cms', function(){
		$('.cms_image_overlay,.cms_image_container').remove();
	});
	
	$('.cms_image_save').on('click.cms', function(){
		
		get_ajax_panel('cms/cms_images_operations', {
			'filename': $(this).data('filename'), 
			'do': 'cms_images_save',
			'author': $('.cms_image_author').val(),
			'copyright': $('.cms_image_copyright').val(),
			'description': $('.cms_image_description').val(),
			'category': $('.cms_image_category').val(),
		}, function(){
			
			if ($('.cms_images_category').val() != $('.cms_image_category').val()){
				$('.cms_images_category').val($('.cms_image_category').val());
				var page = 0;
			} else {
				var page = $('.cms_images_area').data('page');
				if ($('.cms_images_image').length == 1 && page > 0){
					page = page - 1;
				}
			}

			$('.cms_image_overlay,.cms_image_container').remove();
			
			// refresh area
			cms_images_load_images(
					page, 
					$('.cms_images_area').data('limit'), 
					$('.cms_images_area').data('filename')
			);
			
		})
		
	})
	
	// crop
	
	
}

function cms_image_resize(){
	
	var $image = $('.cms_image_image')
	
	var area_width = $image.parent().innerWidth();
	
	$image.css({'width': (area_width * 0.7 + 'px') }).removeClass('cms_image_image_hidden')
	
	
}

function cms_image_scroll(){
	
}

$(document).ready(function() {

	$(window).off('resize.cms_image').on('resize.cms_image', cms_image_resize)
	$(window).off('scroll.cms_image').on('scroll.cms_image', cms_image_scroll)
	
	cms_image_init()
	cms_image_resize()
	cms_image_scroll()

})
