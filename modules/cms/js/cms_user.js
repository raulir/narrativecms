function cms_user_init(){
	
	$('.cms_user_button').on('click.cms', function(){
		
		$('.cms_user_form').get(0).submit();
		
	});
	
}

function cms_user_resize(){
	
	$('body').css({'height': parseInt($(window).innerHeight()) + 'px'});

}

$(document).ready(function() {
		
	$(window).on('resize.cms', function(){
		cms_user_resize();
	});
	
	cms_user_init();
	
	cms_user_resize();

});
