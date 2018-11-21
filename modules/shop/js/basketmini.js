function basketmini_init(){
	
	var $basketmini_content = $('.basketmini_content');
	var height = 0;
	var delta = 0;

	// basketmini to sidefloat
	if (!$('.sidefloat_item_basketmini').length) {
		sidefloat_add('basketmini', $('.basketmini_container'));
	}
	
	$('.sidefloat_item_basketmini').css({'height':'0'});
	
	if (cms_cookie_read('basketmini_open')){
		$('.basketmini_container').addClass('basketmini_state_open');
		$('.basketmini_state_closed').removeClass('basketmini_state_closed');
	}
	
	setTimeout(function(){
		
		height = $basketmini_content.outerHeight();
		delta = height - $('.basketmini_closed').outerHeight();
		
		if ($('.basketmini_container').hasClass('basketmini_state_open')){
			$basketmini_content.css({'height': $('.basketmini_open').outerHeight() + delta + 'px'});
		} else {
			$basketmini_content.css({'height': height + 'px'});
		}

		$('.sidefloat_item_basketmini').css({'height':''});
		
	}, 60);
	
	$('.basketmini_closed_button').on('click.cms', function(){
	
		$('.basketmini_container').addClass('basketmini_state_open');
		$('.basketmini_state_closed').removeClass('basketmini_state_closed');
		cms_cookie_create('basketmini_open', 1, 7);
		
		// adjust height
		$basketmini_content.css({'height': $('.basketmini_open').outerHeight() + delta + 'px'});
	
	});
	
	$('.basketmini_open_close').on('click.cms', function(){
	
		$('.basketmini_container').addClass('basketmini_state_closed');
		$('.basketmini_state_open').removeClass('basketmini_state_open');
		
		// adjust height
		$basketmini_content.css({'height': $('.basketmini_closed').outerHeight() + delta + 'px'});

		cms_cookie_erase('basketmini_open');
		
	});
	
	$('.basketmini_item_delete').on('click.cms', function(){
		
		$('.basketmini_modal').addClass('basketmini_modal_active').data('order_line_id', $(this).closest('.basketmini_item').data('order_line_id'));
		
	});
	
	$('.basketmini_modal_cancel').on('click.cms', function(){
		$('.basketmini_modal_active').removeClass('basketmini_modal_active');
	});
	
	$('.basketmini_modal_yes').on('click.cms', function(){
		
		if ($('.basketmini_modal_active').length){

			get_ajax_panel('shop/order_operations', {
				'do':'delete_order_line',
				'return_id':$(this).closest('.basketmini_container').data('cms_page_panel_id'),
				'id': $(this).closest('.basketmini_modal').data('order_line_id')
			}, function(data){
				
				$('.basketmini_container').replaceWith(data.result.html);
							
			});

		}
		
	});
	
}

function basketmini_resize(){

}

function basketmini_scroll(){
		
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		basketmini_resize();
	});
	
	$(window).on('scroll.cms', function(){
		basketmini_scroll();
	});
	
	basketmini_init();

	basketmini_resize();
	
	basketmini_scroll();

});
