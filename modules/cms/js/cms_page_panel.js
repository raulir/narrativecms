
function init_admin_repeater_block_delete(){
	$('.admin_repeater_block_delete').off('click.r').on('click.r', function(){
		$(this).closest('.admin_repeater_block_toolbar').parent().remove();
	});
}

function admin_block_init(){

	$('.admin_block,.admin_column').each(function(){
		$this = $(this);
		var label = $this.data('label')
		if (label){
			$this.children('.admin_block_label').remove();
			$this.prepend('<div class="admin_block_label"><div class="admin_block_title">' + $this.data('label') + '</div></div>');
		}
	});

}

$(document).ready(function() {
	
	admin_block_init();
	
	$('.admin_block_shortcut_to').on('change.cms', function(){
		$('.admin_block_panel_name').val('');
	})
	
	$('.admin_block_panel_name').on('change.cms', function(){
		$('.admin_block_shortcut_to').val('');
	})

	$('.admin_repeater_button').on('click.r', function(event){
		var block_html = String($(this).data('html'));
		block_html = block_html.replace(/###random###/g, ('0000000'+Math.random().toString(36).replace('.', '')).substr(-8));
		block_html = block_html.replace(/#/g, '"');
		$(this).parent().children('.admin_repeater_line').before(block_html);
		
		if (typeof cms_input_textarea_init == 'function'){
			cms_input_textarea_init();
		}

		init_admin_repeater_block_delete();
		if (typeof cms_input_image_rename == 'function'){
			cms_input_image_rename($(this).data('name') + '_image_');
		}
		
		// init link inputs
		if (typeof cms_input_link_init == 'function'){
			cms_input_link_init();
		}
		
		// init file inputs
		if (typeof cms_input_file_init == 'function'){
			cms_input_file_init();
		}
		
		// init repeater selects
		if (block_html.indexOf('repeater_select') > -1){
			cms_input_repeater_select_init();
		}
		
		$('body').height('auto');
		
	})
	
	init_admin_repeater_block_delete();
	
	$('.admin_repeater_container').sortable().disableSelection();
	
	var title_field = $('.admin_title_text').data('title_field');
	var $title_field = $('textarea,input,select').filter('[name="panel_params[' + title_field + ']"]');
	if ($title_field.length){
		$('.admin_title_text').html(strip_tags($title_field.val()));
		$title_field.on('keyup.r', function(){
			$('.admin_title_text').html(strip_tags($title_field.val()));
		});
	}
	
	// limit length of text inputs
	$('.admin_max_chars').each(function(){
		var $this = $(this);
		$this.off('keyup.r').on('keyup.r', function(){
			var $that = $(this);
			if ($that.val().length > parseInt($that.data('max_chars'))){
				$that.addClass('admin_input_error');
			} else {
				$that.removeClass('admin_input_error');
			}
		})
	});

});
