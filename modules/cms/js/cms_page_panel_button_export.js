function cms_page_panel_button_export_init(){

	$('.cms_page_panel_export').off('click.cms').on('click.cms', function(){
		
		var $this = $(this);
		var cms_page_panel_id = $this.data('cms_page_panel_id');
		
		cms_popup_run('export', function(){
			
			$('.cms_popup_area', '.cms_popup_export').html('Exporting ... ');
			
			get_ajax_panel('cms/cms_page_panel_export', {
				'export_id': cms_page_panel_id,
				'do': 'cms_page_panel_export'
			}, function(data){
				
				$('.cms_popup_area', '.cms_popup_export').html(data.result.html);
				
				$('.cms_page_panel_export_close').on('click.cms', function(){
					$('.cms_popup_cancel').click();
				});
				
			});
			
		});
		
	});

}

function cms_page_panel_button_export_resize(){
		
}

function cms_page_panel_button_export_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panel_button_export_resize();
	});

	$(window).on('scroll.cms', function(){
		cms_page_panel_button_export_scroll();
	});
	
	cms_page_panel_button_export_init();

	cms_page_panel_button_export_resize();
	
	cms_page_panel_button_export_scroll();
	
});
