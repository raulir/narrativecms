
function init_admin_repeater_block_delete(){
	$('.admin_repeater_block_delete').off('click.r').on('click.r', function(){
		$(this).closest('.admin_repeater_block_toolbar').parent().remove();
	});
}

$(document).ready(function() {
	
	$('.cms_table_save').on('click.r', function(event){
		event.stopPropagation();
		$('.admin_form').submit();
		return false;
	});
	
	$('.admin_repeater_button').on('click.r', function(event){
		var block_html = String($(this).data('html'));
		block_html = block_html.replace(/###random###/g, ('0000000'+Math.random().toString(36).replace('.', '')).substr(-8));
		block_html = block_html.replace(/#/g, '"');
		$(this).parent().children('.admin_repeater_line').before(block_html);
		format_admin_textarea();
		init_admin_repeater_block_delete();
		if (typeof cms_input_image_rename == 'function'){
			cms_input_image_rename($(this).data('name') + '_image_');
		}
		
		// if there is a file input
		admin_input_file_init();
		
	})
	
	init_admin_repeater_block_delete();
	
	$('.admin_repeater_container').sortable().disableSelection();

});
