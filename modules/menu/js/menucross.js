function menucross_init(){
/*	
	$('.menucross_container').on('click.cms', function(){
		
		if ($(this).hasClass('menucross_active')){
			$('.menucross_active').removeClass('menucross_active');
		} else {
			$('.menucross_container').addClass('menucross_active');
		}
		
	});
*/
}

function menucross_resize(){
	
}

function menucross_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		menucross_resize();
	});
	
	$(window).on('scroll.cms', function(){
		menucross_scroll();
	});
	
	menucross_init();

	menucross_resize();
	
	menucross_scroll();

});
