function cms_page_panel_caching_init(){
	
	$('.cms_page_panel_caching_add').on('click.cms', function(){
		var value = $('.cms_page_panel_caching_lists_select').val();
		if (value != null){
			$('.cms_page_panel_caching_values').append('<div class="cms_page_panel_caching_item" data-value="' + value + '">' + 
					value + '<div class="cms_page_panel_caching_item_close">x</div></div>');
			$('.cms_page_panel_caching_lists_option_' + value).remove();
			cms_page_panel_caching_item_init();
		}
	});
	
	cms_page_panel_caching_item_init();
	
}

function cms_page_panel_caching_item_init(){
	
	$('.cms_page_panel_caching_item').off('click.cms').on('click.cms', function(){
		var value = $(this).data('value');
		$(this).remove();
		$('.cms_page_panel_caching_lists_select').append('<option class="cms_page_panel_caching_lists_option_' + value + '" value="' + value + '">' + value + '</option>');
	});
	
}

// called from admin_block.js
function cms_page_panel_caching_save(after){
	
	if (!after){
		after = function(){};
	}
	
	// collect data
	var lists = [];
	$('.cms_page_panel_caching_item').each(function(){
		lists.push($(this).data('value'));
	});
	var caching = $('.cms_page_panel_caching_select').val();
	
	get_ajax_panel('cms_page_panel_operations', {
		'do': 'cms_page_panel_caching',
		'lists': lists,
		'caching': caching,
		'target_id': $('.cms_page_panel_caching_target_id').val()
	}, function(data){
		
		// make button bold accordingly
		if(data.result._caching == 1){
			$('.cms_page_panel_caching').addClass('cms_tool_button_active');
		} else {
			$('.cms_page_panel_caching').removeClass('cms_tool_button_active');
		}

		after();
		
	});
	
}

function cms_page_panel_caching_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panel_caching_resize();
	});
	
	cms_page_panel_caching_resize();
	
	cms_page_panel_caching_init();

});
