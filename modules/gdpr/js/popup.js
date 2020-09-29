function popup_init(){
	
	$('.gdpr_popup_upper').on('click.popup', function(){
		
		if (!$('.gdpr_popup_active').length){
			$('.gdpr_popup_container').addClass('gdpr_popup_active');
		} else {
			$('.gdpr_popup_active').removeClass('gdpr_popup_active');
		}
		
	});
	
	$('.gdpr_popup_close').on('click.popup', function(){
		
		$('.gdpr_popup_container').remove();
		
		cms_cookie_create('gdpr', 1, 180);

	});
	
	$('.gdpr_popup_notracking').on('click.popup', function(){
		
		$('.gdpr_popup_container').remove();
		
		cms_cookie_create('gdpr', 1, 7);
		cms_cookie_create('gdpr_notrack', 1, 7);

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
