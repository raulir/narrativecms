function cms_page_panel_button_show_init(){
	
	// defined in panels.js
	activate_cms_page_panel_show();
	
}

function cms_page_panel_button_show_resize(){
		
}

function cms_page_panel_button_show_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panel_button_show_resize();
	});

	$(window).on('scroll.cms', function(){
		cms_page_panel_button_show_scroll();
	});
	
	cms_page_panel_button_show_init();

	cms_page_panel_button_show_resize();
	
	cms_page_panel_button_show_scroll();
	
});
