function init_cms_repeater_block_delete(){
	
	$('.cms_repeater_block_delete').off('click.cms').on('click.cms', function(){

		var $area = $(this).closest('.cms_repeater_area')

		// if repeater is target for repeater selects, repopulate repeater selects
		if ($(this).closest('.cms_repeater_target').length){
			cms_input_repeater_select_reinit();
		}

		// remove repeater block
		$(this).closest('.cms_repeater_block').remove();
		
		if (typeof cms_page_panel_fields_init === 'function') {
			cms_page_panel_fields_init()
		}

		if (typeof cms_input_repeater_sortable_init === 'function'){
			cms_input_repeater_sortable_init($area)
		}
		
	});
	
}

$(() => init_cms_repeater_block_delete())
