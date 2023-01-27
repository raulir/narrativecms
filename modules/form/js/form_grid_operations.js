function form_grid_operations_init(){
	
	$('.form_grid_operations_delete').on('click.cms', function(){

		var $this = $(this)

		get_ajax_panel('cms/cms_popup_yes_no', {'text':'Are you sure?'}, function(data){
			panels_display_popup(data.result._html, {
				'yes': function(){
					get_ajax('form/form_grid_operations', {
						'do':'delete_item',
						'item_id': $this.data('item_id'),
						'success': function(data){
							$this.closest('.cms_grid_row').remove()
						}
					})
				}
			})
		})

	})

}

function form_grid_operations_resize(){



}

$(document).ready(function() {
	
	$(window).on('resize.r', function(){
		form_grid_operations_resize();
	});

	form_grid_operations_resize();
	
	form_grid_operations_init();
	
});