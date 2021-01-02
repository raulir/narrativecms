function cms_grid_editable_init(){
	
	$('.cms_grid_editable_input').on('focus.cms', function(){
		$(this).data('old_value', $(this).val())
	})
	
	$('.cms_grid_editable_input').on('blur.cms', function(){
		
		var $this = $(this)
		
		if ($this.val() != $this.data('old_value')){
		
			var data = {
					'do': 'update_field',
					'item_id': $this.data('item_id'),
					'name': $this.data('name'),
					'value': $this.val()
			}
	
			get_ajax_panel('cms/cms_grid_editable', data, function(result){
	
				$this.closest('.cms_grid_field_inner').html(result.result._html)
				cms_notification('Field ' + $this.data('name') + ' updated', 2)
	
			})
		
		}

	})

}

function cms_grid_editable_resize(){
	
}

function cms_grid_editable_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', cms_grid_editable_resize)
	$(window).on('scroll.cms', cms_grid_editable_scroll)
	
	cms_grid_editable_init()
	cms_grid_editable_resize()
	cms_grid_editable_scroll()

})
