function cms_input_grid_resize_height($grid){

	if (!$grid || !$grid.length){
		return
	}

	var row_count = $grid.find('.cms_grid_row').not('.cms_grid_row_template').length
	var grid_height = 1 + row_count + 2

	if ($grid.find('.cms_grid_new').length){
		grid_height += 1
	}

	$grid.data('cms_input_height', grid_height)

	if (typeof cms_page_panel_fields_init === 'function'){
		cms_page_panel_fields_init()
	}

}

function cms_input_grid_log_read($grid){

	var raw = $grid.find('.cms_grid_log').val() || '[]'
	var log = []

	try {
		log = JSON.parse(raw)
	} catch (e) {
		log = []
	}

	return Array.isArray(log) ? log : []

}

function cms_input_grid_log_write($grid, log){

	$grid.find('.cms_grid_log').val(JSON.stringify(log))

}

function cms_input_grid_log_set($grid, id, field, value){

	id = String(id)
	var log = cms_input_grid_log_read($grid).filter(function(op){
		return !(op && op.op === 'set' && String(op.id) === id && op.field === field)
	})

	log.push({
		op: 'set',
		id: id,
		field: field,
		value: value
	})

	cms_input_grid_log_write($grid, log)

}

function cms_input_grid_log_add($grid, id){

	var log = cms_input_grid_log_read($grid)

	log.push({
		op: 'add',
		id: String(id)
	})

	cms_input_grid_log_write($grid, log)

}

function cms_input_grid_log_delete($grid, id){

	id = String(id)
	var log = cms_input_grid_log_read($grid).filter(function(op){
		return !(op && String(op.id) === id)
	})

	if (id.indexOf('tmp_') !== 0){
		log.push({
			op: 'delete',
			id: id
		})
	}

	cms_input_grid_log_write($grid, log)

}

function cms_input_grid_next_tmp($grid){

	var n = parseInt($grid.data('tmp_n'), 10) || 0
	n += 1
	$grid.data('tmp_n', n)

	return 'tmp_' + n

}

function cms_input_grid_row_id($row){

	var id = $row.find('.cms_grid_row_id').val()
	if (id){
		return String(id)
	}

	id = $row.find('[data-line_id]').first().attr('data-line_id')

	return id ? String(id) : ''

}

function cms_input_grid_stamp_row($row, id){

	id = String(id)
	$row.find('.cms_grid_row_id').val(id)
	$row.find('[data-line_id]').attr('data-line_id', id).data('line_id', id)
	$row.find('[data-item_id]').attr('data-item_id', id).data('item_id', id)

}

function cms_input_grid_after_save(result){

	if (!result){
		return
	}

	if (result.grid_ids){
		$.each(result.grid_ids, function(grid_name, map){
			if (!map || typeof map !== 'object'){
				return
			}
			var $grid = $('.cms_grid_log[data-grid_name="' + grid_name + '"]').closest('.cms_grid_container')
			$.each(map, function(from_id, to_id){
				$grid.find('.cms_grid_row').each(function(){
					var $row = $(this)
					if (cms_input_grid_row_id($row) !== String(from_id)){
						return
					}
					cms_input_grid_stamp_row($row, to_id)
				})
			})
		})
	}

	if (result.grid_log){
		$.each(result.grid_log, function(name, json){
			$('.cms_grid_log[data-grid_name="' + name + '"]').val(json)
		})
	} else if (result.ok !== 0){
		$('.cms_grid_log').val('[]')
	}

}

function cms_input_grid_init($root){

	var $scope = $root ? $root.find('.cms_grid_container') : $('.cms_grid_container');

	$scope.not('.cms_input_grid_ok').each(function(){

		var $grid = $(this);

		$grid.addClass('cms_input_grid_ok');

		$('.cms_grid_new', $grid).on('click.cms', function(e){

			e.preventDefault()

			var id = cms_input_grid_next_tmp($grid)
			var $row = $grid.find('.cms_grid_row_template').first().clone()

			$row.removeClass('cms_grid_row_template').css('display', '')
			$row.find('.dim_value_select_ok, .cms_grid_editable_ok').removeClass('dim_value_select_ok cms_grid_editable_ok')
			cms_input_grid_stamp_row($row, id)
			$grid.find('.cms_grid_area').append($row)
			cms_input_grid_log_add($grid, id)
			cms_input_grid_resize_height($grid)

			if (typeof cms_grid_editable_init === 'function'){
				cms_grid_editable_init($row)
			}

		})

		$grid.on('click.cms', '.cms_grid_delete', function(e){

			e.preventDefault()

			var $this = $(this)

			get_ajax_panel('cms/cms_popup_yes_no', {'text':'Are you sure?'}, function(data){
				panels_display_popup(data.result._html, {
					'yes': function(){
						var $row = $this.closest('.cms_grid_row')
						var id = cms_input_grid_row_id($row)
						if (!id || $row.hasClass('cms_grid_row_template')){
							return
						}
						cms_input_grid_log_delete($grid, id)
						$row.remove()
						cms_input_grid_resize_height($grid)
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
