
function cms_list_init_filters(){
	
	var filters_str = cookie_read($('.cms_list_container').data('panel_name'));
	
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
	
	cookie_create($('.cms_list_container').data('panel_name'), JSON.stringify(filters));
	
	return filters;
	
}

function activate_cms_page_panel_copy(params){
	
	if (typeof params.after !== 'function'){
		params = {
			after: function(){}
		}
	}

	$('.cms_page_panel_copy').off('click.cms').on('click.cms', function(){
		var $this = $(this);
		var cms_page_panel_id = $this.data('cms_page_panel_id');
		get_ajax_panel('cms_page_panel_operations', {
			'cms_page_panel_id': cms_page_panel_id,
			'do': 'cms_page_panel_copy' 
		}, function(data){
			params.after();
		})
	});

}

function cms_list_load(start, limit, after){
	
	if (typeof after != 'function'){
		after = function(){};
	}
	
	// get filters
	var filters = cms_list_save_filters();
	
	var panel_name = $('.cms_list_container').data('panel_name');
	var source = $('.cms_list_container').data('source');
	
	get_ajax_panel('cms_list_list', {
		start: start, 
		limit: limit, 
		edit_base: $('.cms_list_container').data('edit_base'),
		panel_name: panel_name,
		source: source,
		filters: filters,
		id_field: $('.cms_list_container').data('id_field'),
		no_sort: $('.cms_list_container').data('no_sort') //,
		// _no_css: true
	}, function(data){
		
		$('.cms_list_container').html(data.result.html).data({start: start, limit: limit, total: data.result.total });
		
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
		
		if($('.cms_list_container').data('no_sort') != 'no_sort'){
			// activate sorting
			$('.admin_list_sortable').sortable({
	
				'stop':function(event, ui){
					if (!$('.admin_list_sortable').hasClass('admin_list_sortable_cancelled')){
						// save order
						var list_order = {};
						$('.cms_list_sortable_item').each(function(index, value){
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
					cms_list_load($('.cms_list_container').data('start'), $('.cms_list_container').data('limit'));
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
	cms_list_load(0, $('.cms_list_container').data('limit'));
	
	// init filters
	$('.admin_tool_filter').on('change.cms', function(){
		cms_list_load(0, $('.cms_list_container').data('limit'));
	});
	
	if($('.cms_list_container').data('no_sort') != 'no_sort'){
	
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
						cms_list_load($('.cms_list_container').data('start'), $('.cms_list_container').data('limit'));
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
				$('.cms_list_sortable_item').each(function(index, value){
					list_order[index] = $(this).data('block_id');
				});
				get_ajax('cms_list_move', {
					'do': 'cms_list_move', 
					target: 'previous',
					block_id: $(ui.draggable).data('block_id'),
					start: $('.cms_list_container').data('start'), 
					limit: $('.cms_list_container').data('limit'),
					filters: filters,
					list_order: list_order,
					success: function(){
						cms_list_load($('.cms_list_container').data('start'), $('.cms_list_container').data('limit'));
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
				$('.cms_list_sortable_item').each(function(index, value){
					list_order[index] = $(this).data('block_id');
				});
				get_ajax('cms_list_move', {
					'do': 'cms_list_move', 
					target: 'next',
					block_id: $(ui.draggable).data('block_id'),
					start: $('.cms_list_container').data('start'), 
					limit: $('.cms_list_container').data('limit'),
					filters: filters,
					list_order: list_order,
					success: function(){
						cms_list_load($('.cms_list_container').data('start'), $('.cms_list_container').data('limit'));
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
						cms_list_load($('.cms_list_container').data('start'), $('.cms_list_container').data('limit'));
					}
				});
	    	}
	    });
	
	}
	
	$('.cms_list_import').on('click.cms', function(){
		
		// create import popup for this list type
		get_ajax_panel('cms_page_panel_import', {}, function(data){
			
			$('.cms_popup_area', '.cms_popup_import').html(data.result.html);
			
			cms_popup_run('import', function(){
				
				$('.cms_page_panel_import_input').on('change.cms', function(){
					
					var data = new FormData( $('.cms_page_panel_import_form').get(0) );
					data.append('panel_id', 'cms_page_panel_import');
					data.append('do', 'cms_page_panel_import');
					
					$.ajax( {
						url: config_url + 'ajax_api/get_panel',
					    type: 'POST',
					    data: data,
					    processData: false,
					    contentType: false,
					    dataType: 'json',
					    success: function(data){
					    	
					    	$('.cms_page_panel_import_time').html(data.result.time);
					    	$('.cms_page_panel_import_panels').html(data.result.panels);
					    	$('.cms_page_panel_import_images').html(data.result.images);
					    	$('.cms_page_panel_import_files').html(data.result.files);
					    	$('.cms_page_panel_import_new_images_size').html(data.result.new_images_size);
					    	$('.cms_page_panel_import_new_images_count').html(data.result.new_images_count);
					    	
					    	$('.cms_page_panel_import_upload').html('Importing...');
					    	
					    	
					    },
					    xhr: function() {
					        var xhr = new window.XMLHttpRequest();

					        xhr.upload.addEventListener('progress', function(evt) {
					        	if (evt.lengthComputable) {
					        	  
					        		var percentComplete = evt.loaded / evt.total;
					        		percentComplete = parseInt(percentComplete * 100);

					        		$('.cms_page_panel_import_upload').html(percentComplete + '%');

					        	}
					        }, false);

					        return xhr;
					    }
					} );
					
				});
				


				
				
			});
			
		});
		
		/*
		var $this = $(this);
		var cms_page_panel_id = $this.data('cms_page_panel_id');
		
		cms_popup_run('export', function(){
			
			$('.cms_popup_area', '.cms_popup_export').html('Exporting ... ');
			
			get_ajax_panel('cms_page_panel_export', {
				'export_id': cms_page_panel_id,
				'do': 'cms_page_panel_export'
			}, function(data){
				
				$('.cms_popup_area', '.cms_popup_export').html(data.result.html);
				
				$('.cms_page_panel_export_close').on('click.cms', function(){
					$('.cms_popup_cancel').click();
				});
				
			});
			
		});
		*/
		
	});
		
});
