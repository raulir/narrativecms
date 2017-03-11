function cms_input_page_panels_init(){
	
	$('.cms_list_sortable').sortable().disableSelection();
	
	// save before adding a new panel
	$('.cms_input_page_panels_add').on('click.cms', function(){
		
		var $this = $(this);
		var cms_page_id = $('.cms_page_id').val();
		var cms_page_panel_id = $('.cms_page_panel_id').val();

		if ($('.cms_page_panel_id').length == 0 && cms_page_id == 0){
			// if no block id field, then must be on the page admin
			
			// ask are you sure
			get_ajax_panel('cms_popup_yes_no', {
				'text': 'Save page and add block?'
			}, function(data){
				panels_display_popup(data.result.html, {
					'yes': function(){
						
						cms_page_save({
							'success':function(){
								
								if ($('.cms_page_id').val()){
									window.location.href = config_url + 'admin/block/0/' + $('.cms_page_id').val() + '/'
								} else {
									window.location.href = $this.data('target');
								}
							
							}
						});

					}
				}); 
			});
				
		} else if (cms_page_panel_id == 0){
			// is on block admin, but block doesn't have id
			
			// ask are you sure
			get_ajax_panel('cms_popup_yes_no', {
				'text': 'Block is not saved. Save block?'
			}, function(data){
				panels_display_popup(data.result.html, {
					'yes': function(){
						
						cms_page_panel_save({
							'success':function(data){
								
								window.location.href = config_url + 'admin/block/0/0/' + data.result.cms_page_panel_id + '/' + $this.data('name') + '/';
							
							}
						});

					}
				}); 
			});
			
			
		} else {
				
			window.location.href = $this.data('target');

		}
		
	});
	
}

function cms_input_page_panels_resize(){
		
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_input_page_panels_resize();
	});

	cms_input_page_panels_init();

	cms_input_page_panels_resize();
	
});




