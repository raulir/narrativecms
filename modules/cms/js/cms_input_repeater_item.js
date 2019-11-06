function init_cms_repeater_block_delete(){
	
	$('.cms_repeater_block_delete').off('click.cms').on('click.cms', function(){

		// if repeater is target for repeater selects, repopulate repeater selects
		if ($(this).closest('.cms_repeater_target').length){
			cms_input_repeater_select_reinit();
		}

		// remove repeater block
		$(this).closest('.cms_repeater_block').remove();
		
	});
	
}

$(() => init_cms_repeater_block_delete())
