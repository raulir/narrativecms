function cms_input_grid_resize_height($grid){

	if (!$grid || !$grid.length){
		return
	}

	var row_count = $grid.find('.cms_grid_row').length
	var grid_height = 1 + row_count + 2

	if ($grid.find('.cms_grid_new').length){
		grid_height += 1
	}

	$grid.data('cms_input_height', grid_height)

	if (typeof cms_page_panel_fields_init === 'function'){
		cms_page_panel_fields_init()
	}

}

function cms_input_grid_init($root){

	var $scope = $root ? $root.find('.cms_grid_container') : $('.cms_grid_container');

	$scope.not('.cms_input_grid_ok').each(function(){

		var $grid = $(this);

		$grid.addClass('cms_input_grid_ok');

		$('.cms_grid_new', $grid).on('click.cms', function(){
		
		var $this = $(this)
		
		var data = {
				'do': 'create_row',
				'base_id': $this.data('base_id'),
				'ds': $this.data('ds')
		}

		get_ajax_panel('cms/cms_input_grid', data, function(result){

//			$this.closest('.cms_input_container').html(result.result.html)
			console.log(result)
			
			cms_notification('Row added', 2)
			
			setTimeout(() => { location.reload() }, 500)

		})

		})

		$('.cms_grid_delete', $grid).on('click.cms', function(){

		var $this = $(this)

		get_ajax_panel('cms/cms_popup_yes_no', {'text':'Are you sure?'}, function(data){
			panels_display_popup(data.result._html, {
				'yes': function(){
					var $row = $this.closest('.cms_grid_row')
					var $grid = $this.closest('.cms_grid_container')
					var delete_data = {
						'do':'delete_row',
						'ds': $this.data('ds'),
						'id': $this.data('line_id'),
						'base_name': $this.data('base_name'),
						'success': function(data){
							// ds delete returns {web:1}; remove row from DOM
							if (data && !data.error && data.result && data.result.web){
								$row.remove()
								cms_input_grid_resize_height($grid)
							}
						}
					}
					if ($this.data('base_id')){
						delete_data.base_id = $this.data('base_id')
					}
					get_ajax('cms/cms_input_grid', delete_data)
				}
			})
		})

		})

	});

}

function cms_input_grid_resize(){
	
}

function cms_input_grid_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', cms_input_grid_resize)
	$(window).on('scroll.cms', cms_input_grid_scroll)
	
	cms_input_grid_init()
	cms_input_grid_resize()
	cms_input_grid_scroll()

})
