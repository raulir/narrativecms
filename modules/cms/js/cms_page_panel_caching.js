function cms_page_panel_caching_init($root){

	var $scope = $root ? $root.find('.cms_page_panel_caching_container') : $('.cms_page_panel_caching_container');

	$scope.not('.cms_page_panel_caching_ok').each(function(){

		var $panel = $(this);

		$panel.addClass('cms_page_panel_caching_ok');

		$('.cms_page_panel_caching_add', $panel).on('click.cms', function(){
		var value = $('.cms_page_panel_caching_lists_select').val();
		if (value != null){
			$('.cms_page_panel_caching_values').append('<div class="cms_page_panel_caching_item" data-value="' + value + '">' + 
					value + '<div class="cms_page_panel_caching_item_close">x</div></div>');
			// By value: a list name has a "/", so it cannot be part of a class selector
			$('.cms_page_panel_caching_lists_select option').filter(function(){
				return this.value === value;
			}).remove();
			cms_page_panel_caching_item_init($panel);
		}
		});

		cms_page_panel_caching_item_init($panel);

	});

}

function cms_page_panel_caching_item_init($root){

	var $items = $root ? $root.find('.cms_page_panel_caching_item') : $('.cms_page_panel_caching_item');

	$items.not('.cms_page_panel_caching_item_ok').each(function(){

		var $item = $(this);

		$item.addClass('cms_page_panel_caching_item_ok');

		$item.on('click.cms', function(){
			var value = $item.data('value');
			$item.remove();
			$('.cms_page_panel_caching_lists_select').append('<option value="' + value + '">' + value + '</option>');
		});

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
	
	get_ajax_panel('cms/cms_page_panel', {
		'cms_do': 'cms_page_panel_caching',
		'lists': lists,
		'caching': caching,
		'target_id': $('.cms_page_panel_caching_target_id').val()
	}, function(data){

		if (data.result.ok === 0){
			cms_notification(data.result.cms_error || 'Cannot save caching settings', 3, 'error');
			return;
		}

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
