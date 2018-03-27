function export_button_init(){

	$('.export_button_content').on('click.cms', function(){
		
		// call export ajax
		get_ajax('acfsync/export', {
			'do':'export',
			'success':function(){
				cms_notification('Export completed', 3);
			}
		});
		
	});
	
}

function export_button_resize(){

}

$(document).ready(function(){
	
	$(window).on('resize.cms', function(){
		export_button_resize();
	});

	export_button_init($(this));
	
	export_button_resize();

})
