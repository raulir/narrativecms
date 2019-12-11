function cms_page_panel_button_show_activate(){
	$('.cms_page_panel_show').off('click.cms').on('click.cms', function(){
		
		var action = function($this){
			var cms_page_panel_id = $this.data('cms_page_panel_id');
			get_ajax_panel('cms_page_panel_operations', {
				'cms_page_panel_id': cms_page_panel_id,
				'do': 'cms_page_panel_show'
			}, function(data){
				if (data.result.show == 1){
					$this.closest('li').removeClass('cms_item_hidden');
					$this.html('hide');
				} else {
					$this.closest('li').addClass('cms_item_hidden');
					$this.html('show');
				}
			});
			
		}
		
		var $this = $(this);

		if ($this.html().trim() == 'show'){
			
			// check if all mandatory is filled in
			if (typeof cms_page_panel_check_mandatory == 'function'){
				var mandatory_result = cms_page_panel_check_mandatory('red');
			} else {
				var mandatory_result = [];
			}
			
			if (mandatory_result.length){

				var mandatory_extra = cms_page_panel_format_mandatory(mandatory_result, 'red');
				cms_notification('Error showing panel' + mandatory_extra, 3, 'error')

			} else {

				// ask are you sure
				get_ajax_panel('cms_popup_yes_no', {}, function(data){
					panels_display_popup(data.result.html, {
						'yes': function(){
							
							// if save button, save 
							if ($('.cms_page_panel_save').length){
								
								cms_page_panel_save({
									'no_mandatory_check': true,
									'success':function(data){
										action($this);
									}
								})
							
							} else {
							
								action($this)
							
							}
							
						}
					}); 
				});

			}
			
		} else {
			action($this);
		}

	});
}


function cms_page_panel_button_show_init(){
	
	cms_page_panel_button_show_activate();
	
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
