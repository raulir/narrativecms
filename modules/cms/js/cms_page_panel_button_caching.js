function cms_page_panel_button_caching_init($root){

	var $scope = $root ? $root.find('.cms_page_panel_caching') : $('.cms_page_panel_caching');

	$scope.not('.cms_page_panel_button_caching_ok').each(function(){

		var $button = $(this);

		$button.addClass('cms_page_panel_button_caching_ok');

		$button.on('click.cms', function(){

		get_ajax_panel('cms/cms_page_panel_caching', {'target_id':$(this).data('cms_page_panel_id')}, function(data){

			panels_display_popup(data.result._html, {
				'yes': function(after){

					// defined in panel js file
					cms_page_panel_caching_save(function(){
						cms_notification('Panel caching settings saved', 3);
					});
				},
				'cancel': function(after){
					after();
				}
			}); 			
		});
		
		});

	});

}

function cms_page_panel_button_caching_resize(){
		
}

function cms_page_panel_button_caching_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panel_button_caching_resize();
	});

	$(window).on('scroll.cms', function(){
		cms_page_panel_button_caching_scroll();
	});
	
	cms_page_panel_button_caching_init();

	cms_page_panel_button_caching_resize();
	
	cms_page_panel_button_caching_scroll();
	
});
