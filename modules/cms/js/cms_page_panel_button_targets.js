function cms_page_panel_button_targets_init(){

	$('.cms_page_panel_button_targets').off('click.cms').on('click.cms', function(){
		
		var $this = $(this);
		var cms_page_panel_id = $this.data('cms_page_panel_id');
		
		cms_popup_run('targets', function(){
			
			$('.cms_popup_area', '.cms_popup_targets').html('loading ... ');
			
			get_ajax_panel('cms/cms_page_panel_targets', {
				'targets_id': cms_page_panel_id,
				'do': 'cms_page_panel_targets'
			}, function(data){
				
				$('.cms_popup_area', '.cms_popup_targets').html(data.result.html);
				
				$('.cms_page_panel_targets_close').on('click.cms', function(){
					
					var $this = $(this);
					
					// collect data
					var data = {};
					$('cms_page_panel_targets_select').each(function(){
						
						data[$this.data('group')] = $this.val();
					
					});
					
					get_ajax('cms/cms_page_panel_targets', {
							'targets_id': cms_page_panel_id,
							'do': 'cms_page_panel_targets',
							'data': '',
							'success': function(){
								$('.cms_popup_cancel').click();
								cms_notification('Visitor target group visibility saved', 3);
							}
					});
					
				});
				
			});
			
		});
		
	});

}

function cms_page_panel_button_targets_resize(){
		
}

function cms_page_panel_button_targets_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panel_button_targets_resize();
	});

	$(window).on('scroll.cms', function(){
		cms_page_panel_button_targets_scroll();
	});
	
	cms_page_panel_button_targets_init();

	cms_page_panel_button_targets_resize();
	
	cms_page_panel_button_targets_scroll();
	
});
