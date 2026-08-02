function menu_init(){
	
	$('.cms_scroll_to').cms_scroll_to(); //{'$space':$('.menu_container')});
	
}

function menu_resize(){

}

function menu_scroll(){
	
	var scrolltop = self.pageYOffset || document.documentElement.scrollTop  || document.body.scrollTop;

	if (scrolltop >= $('.menu_container').offset().top){
		$('.menu_container').addClass('menu_fixed');
	} else {
		$('.menu_container').removeClass('menu_fixed');
	}
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		menu_resize();
	});
	
	$(window).on('scroll.cms', function(){
		menu_scroll();
	});
	
	menu_init();

	menu_resize();
	
	menu_scroll();

});
