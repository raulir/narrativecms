function dim_value_select_init(){
	
	$('.dim_value_select_select').on('change.cms', function(){
		
		var $this = $(this)
		
		var data = {
				'do': 'set_dim',
				'item_id': $this.data('item_id'),
				'dimension': $this.data('dimension'),
				'value': $this.val()
		}

		get_ajax_panel('stock/dim_value_select', data, function(result){

			$this.closest('.cms_grid_field_inner').html(result.result.html)
			
			cms_notification('Dimension ' + $this.data('dimension') + ' updated', 2)

		})

	})

}

function dim_value_select_resize(){
	
}

function dim_value_select_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', dim_value_select_resize)
	$(window).on('scroll.cms', dim_value_select_scroll)
	
	dim_value_select_init()
	dim_value_select_resize()
	dim_value_select_scroll()

})
