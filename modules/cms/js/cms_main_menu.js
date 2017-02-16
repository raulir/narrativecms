
var admin_main_menu_template = '';

function admin_main_menu_delete_init(){
	$('.admin_main_menu_item_delete').off('click.r').on('click.r', function(){
		$(this).closest('li').remove();
	})
}

function admin_main_menu_prepare(template, data){
	
	$.each(data, function(key, value){
		var re = new RegExp('###' + key + '###', 'g');
		template = template.replace(re, value);
	});
	
	return template;
	
}

function admin_main_menu_update_blocks_select($this, anchor){
	
	$this.html('<option value="0">-- no block --</option>');
	
	var val = '';
	$.each(admin_main_menu_page_panels, function(panel_key, panel_value){
		if (parseInt(panel_value.page_id) == parseInt($this.siblings('.admin_main_menu_target_select').val())){
			$this.append('<option value="' + panel_value.page_panel_id + '">' + panel_value.submenu_title + '</option>');
			if (panel_value.submenu_anchor == anchor){
				val = panel_value.page_panel_id;
			}
		}
	});
	
	// select correct
	$('option', $this).removeAttr('selected').filter('[value="' + val + '"]').attr('selected', true);

}

function admin_main_menu_list_item_activate($menu_list_item){
	
	// init page selectors
	$('.admin_main_menu_target_select', $menu_list_item).each(function(){

		var $this = $(this);

		// load current link
		var menu_item_link = $this.data('menu_item_link');
		if (menu_item_link == ''){
			menu_item_link = '/';
		}
		
		// add values
		var val = '';
		$.each(admin_main_menu_pages, function(page_key, page_value){
			$this.append('<option value="' + page_value.page_id + '">' + page_value.title + '</option>');
			if (menu_item_link.substr(0, (page_value.slug + '/').length) == (page_value.slug + '/')){
				val = page_value.page_id;
			}
		});
		
		// select correct
		$('option', $this).removeAttr('selected').filter('[value="' + val + '"]').attr('selected', true);
		
		// when changed, update block select accordingly
		$this.on('change.r', function(){
			admin_main_menu_update_blocks_select($(this).siblings('.admin_main_menu_block_select'), '');
		});

	});
	
	// init block selectors
	$('.admin_main_menu_block_select', $menu_list_item).each(function(){
		
		var $this = $(this);
		
		// load current link
		var menu_item_link = $this.data('menu_item_link');
		if (menu_item_link == ''){
			menu_item_link = '/';
		}
		var parts = menu_item_link.split('#');
		var anchor = '';
		if (typeof parts[1] != 'undefined'){
			anchor = parts[1];
		} else {
			anchor = '';
		}
		
		// add values
		admin_main_menu_update_blocks_select($this, anchor);

	});
	
	// init new window and hide from menu selects
	$('.admin_menu_new_window', $menu_list_item).each(function(){
		var $this = $(this);
		$('option', $this).removeAttr('selected').filter('[value="' + $this.data('menu_item_new_window') + '"]').attr('selected', true);
	});
	
	$('.admin_menu_hide_from_menu', $menu_list_item).each(function(){
		var $this = $(this);
		$('option', $this).removeAttr('selected').filter('[value="' + $this.data('menu_item_hide_from_menu') + '"]').attr('selected', true);
	});
	
	// submenu button
	$('.admin_main_menu_item_submenu', $menu_list_item).each(function(){
		var $this = $(this);
		$this.html($this.data('is_submenu') == '0' ? 'add submenu' : 'edit submenu');
	});
	
	// mode button
	$('.admin_main_menu_mode_select', $menu_list_item).each(function(){
		var $this = $(this);
		$('option', $this).removeAttr('selected').filter('[value="' + $this.data('selected') + '"]').attr('selected', true);
		$this.on('change.r', function(){
			set_mode($menu_list_item);
		});
	});
	
	set_mode($menu_list_item);

}

function set_mode($menu_list_item){
	
	var mode = parseInt($('.admin_main_menu_mode_select', $menu_list_item).val());
	
	if (mode == 0){
		
		// manual
		$('.admin_main_menu_target_select', $menu_list_item).attr('disabled', 'disabled');
		$('.admin_main_menu_block_select', $menu_list_item).attr('disabled', 'disabled');
		$('.admin_menu_input_text', $menu_list_item).removeAttr('disabled');
		$('.admin_menu_input_link', $menu_list_item).removeAttr('disabled');
	
	} else if (mode == 1){
	
		// automatic
		$('.admin_main_menu_target_select', $menu_list_item).removeAttr('disabled');
		$('.admin_main_menu_block_select', $menu_list_item).removeAttr('disabled');
		$('.admin_menu_input_text', $menu_list_item).attr('disabled', 'disabled');
		$('.admin_menu_input_link', $menu_list_item).attr('disabled', 'disabled');
		
	} else {
		
		// automatic link
		$('.admin_main_menu_target_select', $menu_list_item).removeAttr('disabled');
		$('.admin_main_menu_block_select', $menu_list_item).removeAttr('disabled');
		$('.admin_menu_input_text', $menu_list_item).removeAttr('disabled');
		$('.admin_menu_input_link', $menu_list_item).attr('disabled', 'disabled');
		
	}
	
}

function admin_main_menu_init() {
	
	// load template
	admin_main_menu_template = $('.menu_item_template').html();

	// populate area
	if (typeof admin_main_menu_items[0] != 'undefined'){
		$('.menu_items_list').html('');
		$.each(admin_main_menu_items, function(key, value){
			var html = admin_main_menu_prepare(admin_main_menu_template, value);
			$('.menu_items_list').append(html);
		});
	}
	
	// activate freshly added blocks
	$('.menu_items_list>li').each(function(){
		admin_main_menu_list_item_activate($(this));		
	});
	
	// page general buttons
	$('.admin_main_menu_save_button').on('click.r', function(){
		$('.admin_main_menu_form').submit();
	});
	
	$('.admin_main_menu_add_button').on('click.r', function(){
		
		// remove div inside list if there was empty list message
		$('.menu_items_list>div').remove();
		
		// load template
		admin_main_menu_template = $('.menu_item_template').html();

		// populate area with empty data
		var html = admin_main_menu_prepare(admin_main_menu_template, {
			'menu_item_id': '0',
			'menu_id': '', // not used
			'sort': '0',
			'mode': '0',
			'link': '',
			'text': '',
			'new_window': '0',
			'hide_from_menu': '0',
			'is_submenu': '0'
		});
		$('.menu_items_list').append(html);
		
		admin_main_menu_list_item_activate($('.menu_items_list').last());
		
		admin_main_menu_delete_init();
		
		$('.menu_items_list').sortable().disableSelection();

	});
	
	admin_main_menu_delete_init();

	$('.menu_items_list').sortable().disableSelection();

}

$(document).ready(function() {
	
	admin_main_menu_init();
	
});