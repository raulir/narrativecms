function cms_page_toolbar_title(){

	var page_title = $('.cms_page_title').val();
	
	if (page_title == ''){
		page_title = '[ no title ]';
	}
	
	if (page_title.length > 50){
		page_title = page_title.substr(0, 48) + '..';
	}
	
	$('.cms_page_toolbar_title').html(page_title);

}

function cms_page_save(params){
	
	params = params || {'success':function(){}};
	
	get_ajax('cms_page_operations', {
		'page_id': $('.cms_page_id').val(),
		'do': 'cms_page_save',
		'sort': $('.cms_page_sort').val(),
		'title': $('.cms_page_title').val(),
		'slug': $('.cms_page_slug').val(),
		'description': $('.cms_page_description').val(),
		'image': $('.cms_page_image').val(),
		'layout': $('.cms_page_layout').val(),
		'success': function(data){
			
			// update possible changes on form
			$('.cms_page_id').val(data.result.cms_page_id)
			$('.cms_page_slug').val(data.result.slug),
			cms_notification('Page saved', 3);
			
			// update url in browser when page has changed
			change_url(config_url + 'admin/page/' + $('.cms_page_id').val() + '/');
			
			params.success();
			
		}
	})

}

function cms_page_delete(){

	get_ajax_panel('cms_popup_yes_no', {}, function(data){
		panels_display_popup(data.result.html, {
			'yes': function(){
				
				var page_id = $('.cms_page_id').val();
				
				// if empty, page doesn't exist in database
				if (page_id > 0){ 
					get_ajax('cms_page_operations', {
						'page_id': page_id,
						'do': 'cms_page_delete',
						'success': function(data){
							window.location.href = config_url + 'admin/pages/';
						}
					})
				} else {
					window.location.href = config_url + 'admin/pages/';
				}
				
			}
		}); 
	});

}

function cms_page_init(){

	$('.cms_page_save').on('click.cms', function(){
		cms_page_save();
	});
	
	$('.cms_page_delete').on('click.cms', function(){
		cms_page_delete();
	});
	
	cms_page_toolbar_title();
	$('.cms_page_title').on('keyup.cms', cms_page_toolbar_title);
	
	$('.cms_page_panel_delete').on('click.cms', function(){
		var $this = $(this);
		var cms_page_panel_id = $this.data('cms_page_panel_id');
		get_ajax_panel('cms_popup_yes_no', {'text':'Delete block shortcut?'}, function(data){
			panels_display_popup(data.result.html, {
				'yes': function(){
					get_ajax_panel('admin_block_delete', {
						'block_id': cms_page_panel_id,
						'do': 'admin_block_delete' 
					}, function(){
						$this.closest('li').remove();
					})
				}
			}); 
		});
	});
	
	// cms page saves block order automatically
	$('.cms_page_sortable').sortable({
		'stop': function(event, ui){
			// save order
			var block_orders = {};
			$('.cms_page_sortable .block_id').each(function(index, value){
				block_orders[$(this).val()] = index + 1;
			});
			get_ajax('admin_save_block_order', {
				'do': 'admin_save_block_order',
				'block_orders': block_orders,
				'page_id': $('#page_id').val()
			});
		},
	}).disableSelection();
	
}

function cms_page_resize(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_resize();
	});

	cms_page_init();

	cms_page_resize();

});
