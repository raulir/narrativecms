function basketmini_init(){
	
	var $basketmini_content = $('.basketmini_content');
	var height = 0;
	var delta = 0;

	setTimeout(function(){
		
		height = $basketmini_content.outerHeight();
		$basketmini_content.css({'height': height + 'px'});
		delta = height - $('.basketmini_closed').outerHeight();
		
	}, 60);
	
	// basketmini to sidefloat
	sidefloat_add('basketmini', $('.basketmini_container'));
	$('.basketmini_container').removeClass('basketmini_container_hidden');
	
	$('.basketmini_closed_button').on('click.cms', function(){
		
		$('.basketmini_container').addClass('basketmini_state_open');
		$('.basketmini_state_closed').removeClass('basketmini_state_closed');
		
		// adjust height
		$basketmini_content.css({'height': $('.basketmini_open').outerHeight() + delta + 'px'});
		
	});
	
	$('.basketmini_open_close').on('click.cms', function(){
	
		$('.basketmini_container').addClass('basketmini_state_closed');
		$('.basketmini_state_open').removeClass('basketmini_state_open');
		
		// adjust height
		$basketmini_content.css({'height': $('.basketmini_closed').outerHeight() + delta + 'px'});

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
