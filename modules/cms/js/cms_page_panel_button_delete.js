function cms_page_panel_button_delete_init(){
	
	$('.cms_page_panel_button_delete').on('click.cms', function(event){
		// ask are you sure
		get_ajax_panel('cms_popup_yes_no', {}, function(data){
			panels_display_popup(data.result.html, {
				'yes': function(){
					get_ajax_panel('admin_block_delete', {
						'block_id': $('[name="block_id"]').val(), 
						'do': 'admin_block_delete' 
					}, function(data){
						
						$('a.cms_page_panel_toolbar_text').last()[0].click();

					})
				}
			}); 
		});
	});

}

function cms_page_panel_button_delete_resize(){
		
}

function cms_page_panel_button_delete_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panel_button_delete_resize();
	});

	$(window).on('scroll.cms', function(){
		cms_page_panel_button_delete_scroll();
	});
	
	cms_page_panel_button_delete_init();

	cms_page_panel_button_delete_resize();
	
	cms_page_panel_button_delete_scroll();
	
});
