function cms_page_panel_button_targets_bind_save(cms_page_panel_id){

	$('.cms_popup_targets .cms_page_panel_targets_close').off('click.cms').on('click.cms', function(){
		
		var data = {};
		$('.cms_page_panel_targets_select').each(function(){
			
			var $select = $(this);
			data[$select.data('group')] = $select.val();
		
		});
		
		get_ajax('cms/cms_page_panel_targets', {
				'targets_id': cms_page_panel_id,
				'do': 'cms_page_panel_targets',
				'data': data,
				'success': function(response){
					if (response.result && response.result._title){
						cms_page_panel_apply_breadcrumb_title(response.result._title)
					}
					$('.cms_popup_cancel').click();
					cms_notification('Visitor target group visibility saved', 3);
				}
		});
		
	});

}

function cms_page_panel_button_targets_init(){

	$('.cms_page_panel_button_targets').off('click.cms').on('click.cms', function(){
		
		var $this = $(this);
		var cms_page_panel_id = $this.data('cms_page_panel_id');
		
		cms_popup_run('targets', function(){
			
			$('.cms_popup_area', '.cms_popup_targets').html('loading ... ');
			
			cms_page_panel_button_targets_bind_save(cms_page_panel_id);
			
			get_ajax_panel('cms/cms_page_panel_targets', {
				'targets_id': cms_page_panel_id,
				'do': 'cms_page_panel_targets'
			}, function(data){
				
				$('.cms_popup_area', '.cms_popup_targets').html(data.result._html);
				
				cms_page_panel_button_targets_bind_save(cms_page_panel_id);
				
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