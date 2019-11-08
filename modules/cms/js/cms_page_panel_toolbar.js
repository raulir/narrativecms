function cms_page_panel_toolbar_init(){
	
}

function cms_page_panel_toolbar_resize(){
	
}

function cms_page_panel_toolbar_scroll(){
	
	if ($(window).scrollTop() >= (2.5 * _cms_rem)){
		$('.cms_page_panel_toolbar_container').addClass('cms_page_panel_toolbar_fixed');
	} else {
		$('.cms_page_panel_toolbar_container').removeClass('cms_page_panel_toolbar_fixed');
	}
	
}

$(function() {

	$(window).on('resize.cms', cms_page_panel_toolbar_resize);
	
	$(window).on('scroll.cms', cms_page_panel_toolbar_scroll);
	
	cms_page_panel_toolbar_init();

	cms_page_panel_toolbar_resize();
	
	cms_page_panel_toolbar_scroll();

});
