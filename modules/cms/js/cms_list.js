
function cms_list_init_filters(){
	
	var filters_str = cookie_read($('.admin_list_container').data('panel_name'));
	
	if (!filters_str){
		return {};
	}
	
	var filters = JSON.parse(filters_str);

	$('.admin_tool_filter').each(function(){
		
		if (typeof filters[$(this).data('field')] != 'undefined'){
			if ($(this).val() != filters[$(this).data('field')]){
				$(this).val(filters[$(this).data('field')]);
			}
		} else {
			$(this).val('_empty_');
		}
		
	});
	
	return filters;
	
}

function cms_list_save_filters(){
	
	var filters = {};
	$('.admin_tool_filter').each(function(){
		if ($(this).val() != '_empty_'){
			filters[$(this).data('field')] = $(this).val();
		}
	});
	
	cookie_create($('.admin_list_container').data('panel_name'), JSON.stringify(filters));
	
	return filters;
	
}

function cms_list_load(start, limit, after){
	
	if (typeof after != 'function'){
		after = function(){};
	}
	
	// get filters
	var filters = cms_list_save_filters();
	
	var panel_name = $('.admin_list_container').data('panel_name');
	var source = $('.admin_list_container').data('source');
	
	get_ajax_panel('cms_list_list', {
		start: start, 
		limit: limit, 
		edit_base: $('.admin_list_container').data('edit_base'),
		panel_name: panel_name,
		source: source,
		filters: filters,
		title_field: $('.admin_list_container').data('title_field'),
		title_panel: $('.admin_list_container').data('title_panel'),
		id_field: $('.admin_list_container').data('id_field'),
		no_sort: $('.admin_list_container').data('no_sort'),
		_no_css: true
	}, function(data){
		
		$('.admin_list_container').html(data.result.html).data({start: start, limit: limit, total: data.result.total });
		
		after();

		if (start + limit < data.result.total){
			$('.cms_paging_last').off('click.r').on('click.r', function(){
				cms_list_load(Math.floor((data.result.total-1)/limit)*limit, limit);
			}).css({opacity:1});
			$('.cms_paging_next').off('click.r').on('click.r', function(){
				cms_list_load(start+limit, limit);
			}).css({opacity:1});
		} else {
			$('.cms_paging_last,.cms_paging_next').off('click.r').css({opacity:0.3});
		}
		
		if (start > 0){
			$('.cms_paging_first').off('click.r').on('click.r', function(){
				cms_list_load(0, limit);
			}).css({opacity:1});
			$('.cms_paging_previous').off('click.r').on('click.r', function(){
				cms_list_load(Math.max(start - limit, 0), limit);
			}).css({opacity:1});
		} else {
			$('.cms_paging_first,.cms_paging_previous').off('click.r').css({opacity:0.3});
		}
		
		if($('.admin_list_container').data('no_sort') != 'no_sort'){
			// activate sorting
			$('.admin_list_sortable').sortable({
	
				'stop':function(event, ui){
					if (!$('.admin_list_sortable').hasClass('admin_list_sortable_cancelled')){
						// save order
						var list_order = {};
						$('.admin_list_sortable li').each(function(index, value){
							list_order[index] = $(this).data('block_id');
						});
						get_ajax('cms_list_save_order', {'do': 'cms_list_save_order', list_order: list_order});
					}
				}
	
			}).disableSelection();
		}
		
		// any set buttons
		$('.cms_list_set').on('click.cms', function(){
			get_ajax('cms_list_operations', {
				'do': 'cms_list_set', 
				'id': $(this).data('id'), 
				'field': $(this).data('field'), 
				'value': $(this).data('value'), 
				'success': function(){
					cms_list_load($('.admin_list_container').data('start'), $('.admin_list_container').data('limit'));
				}
			});
		});
		
		$('.admin_paging_current').html(parseInt(start/limit) + 1);
		if (parseInt(data.result.total) > 0){
			$('.admin_paging_total').html(Math.floor(parseInt(data.result.total - 1)/limit) + 1);
		} else {
			$('.admin_paging_total').html('1');
		}
		
		$('body').css({'height':'auto'});
		
		// activate list buttons
		setTimeout(function(){
			activate_cms_page_panel_show();
			activate_cms_page_panel_copy({'after':function(){
				cms_list_load(start, limit, after);
			}});
		}, 200);

	});
}

$(document).ready(function() {
	
	cms_list_init_filters();

	// load initially
	cms_list_load(0, $('.admin_list_container').data('limit'));
	
	// init filters
	$('.admin_tool_filter').on('change.cms', function(){
		cms_list_load(0, $('.admin_list_container').data('limit'));
	});
	
	if($('.admin_list_container').data('no_sort') != 'no_sort'){
	
		// dropping to the other pages
		$('.cms_paging_first').droppable({
			accept: '.block_dragable',
	    	activeClass: 'admin_paging_active', // when drag starts
	    	hoverClass: 'admin_paging_hover', // when it is possible to drop here
	    	tolerance: 'pointer',
	    	drop: function( event, ui ) {
	    		$('.admin_list_sortable').addClass('admin_list_sortable_cancelled');
				get_ajax('cms_list_move', {
					'do': 'cms_list_move', 
					target: 'first',
					block_id: $(ui.draggable).data('block_id'), 
					success: function(){
						cms_list_load($('.admin_list_container').data('start'), $('.admin_list_container').data('limit'));
					}
				});
	    	}
	    });
		$('.cms_paging_previous').droppable({
			accept: '.block_dragable',
	    	activeClass: 'admin_paging_active', // when drag starts
	    	hoverClass: 'admin_paging_hover', // when it is possible to drop here
	    	tolerance: 'pointer',
	    	drop: function( event, ui ) {
	    		$('.admin_list_sortable').addClass('admin_list_sortable_cancelled');
	    		var filters = {};
	    		$('.admin_tool_filter').each(function(){
	    			if ($(this).val() != '_empty_'){
	    				filters[$(this).data('field')] = $(this).val();
	    			}
	    		});
				var list_order = {};
				$('.admin_list_sortable li').each(function(index, value){
					list_order[index] = $(this).data('block_id');
				});
				get_ajax('cms_list_move', {
					'do': 'cms_list_move', 
					target: 'previous',
					block_id: $(ui.draggable).data('block_id'),
					start: $('.admin_list_container').data('start'), 
					limit: $('.admin_list_container').data('limit'),
					filters: filters,
					list_order: list_order,
					success: function(){
						cms_list_load($('.admin_list_container').data('start'), $('.admin_list_container').data('limit'));
					}
				});
	    	}
	    });
		$('.cms_paging_next').droppable({
			accept: '.block_dragable',
	    	activeClass: 'admin_paging_active', // when drag starts
	    	hoverClass: 'admin_paging_hover', // when it is possible to drop here
	    	tolerance: 'pointer',
	    	drop: function( event, ui ) {
	    		$('.admin_list_sortable').addClass('admin_list_sortable_cancelled');
	    		var filters = {};
	    		$('.admin_tool_filter').each(function(){
	    			if ($(this).val() != '_empty_'){
	    				filters[$(this).data('field')] = $(this).val();
	    			}
	    		});
				var list_order = {};
				$('.admin_list_sortable li').each(function(index, value){
					list_order[index] = $(this).data('block_id');
				});
				get_ajax('cms_list_move', {
					'do': 'cms_list_move', 
					target: 'next',
					block_id: $(ui.draggable).data('block_id'),
					start: $('.admin_list_container').data('start'), 
					limit: $('.admin_list_container').data('limit'),
					filters: filters,
					list_order: list_order,
					success: function(){
						cms_list_load($('.admin_list_container').data('start'), $('.admin_list_container').data('limit'));
					}
				});
	    	}
	    });
		$('.cms_paging_last').droppable({
			accept: '.block_dragable',
	    	activeClass: 'admin_paging_active', // when drag starts
	    	hoverClass: 'admin_paging_hover', // when it is possible to drop here
	    	tolerance: 'pointer',
	    	drop: function( event, ui ) {
	    		$('.admin_list_sortable').addClass('admin_list_sortable_cancelled');
				get_ajax('cms_list_move', {
					'do': 'cms_list_move', 
					target: 'last',
					block_id: $(ui.draggable).data('block_id'),
					success: function(){
						cms_list_load($('.admin_list_container').data('start'), $('.admin_list_container').data('limit'));
					}
				});
	    	}
	    });
	
	}
		
});
