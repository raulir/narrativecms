function dim_value_select_init(){

	if (dim_value_select_init.bound){
		return
	}
	dim_value_select_init.bound = true

	// Only used inside the product Variants grid: record the change in the grid log.
	$(document).on('change.cms_dim_value_select', '.dim_value_select_select', function(){

		var $this = $(this)
		var $grid = $this.closest('.cms_grid_container')
		var row_id = cms_input_grid_row_id($this.closest('.cms_grid_row'))

		cms_input_grid_log_set($grid, row_id, 'dim_' + $this.data('dim'), $this.val())

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
