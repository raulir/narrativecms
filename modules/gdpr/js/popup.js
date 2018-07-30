function popup_init(){
	
	$('.popup_upper').on('click.popup', function(){
		
		if (!$('.popup_active').length){
			$('.popup_container').addClass('popup_active');
		} else {
			$('.popup_active').removeClass('popup_active');
		}
		
	});
	
	$('.popup_close').on('click.popup', function(){
		
		$('.popup_container').remove();
		
		cookie_create('gdpr', 1, 180);

	});
	
}

function popup_resize(){
	
}

function popup_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.gdpr', function(){
		popup_resize();
	});

	$(window).on('resize.gdpr', function(){
		popup_scroll();
	});

	popup_init();
	
	popup_resize();
	
	popup_scroll();
	
});
