function cms_popup_init(){

	$('.cms_popup_yes').on('click.r', function(){
		$('.cms_popup_container').remove();
	});
	
	$('.cms_popup_no').on('click.r', function(){
		$('.cms_popup_container').remove();
	});
	
	$('.cms_popup_container').css({'opacity':'1'});

}

function cms_popup_resize(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_popup_resize();
	});

	cms_popup_init();

	cms_popup_resize();

});
