function cms_grid_editable_init($root){

	var $scope = $root ? $root.find('.cms_grid_editable_container') : $('.cms_grid_editable_container');

	$scope.not('.cms_grid_editable_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_grid_editable_ok');

		$('.cms_grid_editable_input', $container).on('focus.cms', function(){
			$(this).data('old_value', $(this).val())
		})

		$('.cms_grid_editable_input', $container).on('blur.cms', function(){

			var $this = $(this)

			if ($this.val() != $this.data('old_value')){

				var $grid = $this.closest('.cms_grid_container')
				if ($grid.find('.cms_grid_log').length && typeof cms_input_grid_log_set === 'function'){
					var row_id = (typeof cms_input_grid_row_id === 'function')
						? cms_input_grid_row_id($this.closest('.cms_grid_row'))
						: $this.attr('data-item_id')
					cms_input_grid_log_set($grid, row_id, $this.attr('data-name'), $this.val())
					$this.data('old_value', $this.val())
					return
				}

				var data = {
						'do': 'update_field',
						'item_id': $this.data('item_id'),
						'name': $this.data('name'),
						'value': $this.val()
				}

				if ($this.data('base_id')){
					data.base_id = $this.data('base_id')
				}

				if ($this.data('ds')){
					data.ds = $this.data('ds')
				}

				get_ajax_panel('cms/cms_grid_editable', data, function(result){

					var html = ''
					if (result && result.result){
						html = result.result.html || result.result._html || ''
					}
					var $parsed = $('<div>').html(html)
					var err = $parsed.find('.cms_grid_editable_container').attr('data-save_error') || ''
					var $cell = $this.closest('.cms_grid_field_inner')

					$cell.html(html)
					cms_grid_editable_init($cell)

					if (err){
						cms_notification(err, 4)
					} else {
						cms_notification('Field ' + $this.data('name') + ' updated', 2)
					}

				})

			}

		})

	});

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