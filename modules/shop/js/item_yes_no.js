function item_yes_no_init(){

	if (item_yes_no_init.bound){
		return
	}
	item_yes_no_init.bound = true

	$(document).on('change.cms_item_yes_no', '.item_yes_no_select', function(){

		var $this = $(this)
		var $grid = $this.closest('.cms_grid_container')
		if (!$grid.find('.cms_grid_log').length || typeof cms_input_grid_log_set !== 'function'){
			return
		}

		var row_id = (typeof cms_input_grid_row_id === 'function')
			? cms_input_grid_row_id($this.closest('.cms_grid_row'))
			: $this.attr('data-item_id')

		cms_input_grid_log_set($grid, row_id, $this.attr('data-field'), $this.val())

	})

}

function item_yes_no_resize(){

}

function item_yes_no_scroll(){

}

$(document).ready(function(){

	item_yes_no_init()
	item_yes_no_resize()
	item_yes_no_scroll()

})
