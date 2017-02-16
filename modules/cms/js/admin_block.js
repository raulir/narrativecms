
function init_admin_repeater_block_delete(){
	$('.admin_repeater_block_delete').off('click.r').on('click.r', function(){
		$(this).closest('.admin_repeater_block_toolbar').parent().remove();
	});
}

function init_admin_repeater_select(){
	setTimeout(function(){
		$('.repeater_select').each( function(){
			
			// on change update data to selected value to restore it in future
			
			/*
			if ($('select', $this).data('html')){
				$('select', $this).html($('select', $this).data('html'));
			}
			*/
			
			var $this = $(this);
			
			if ($this.data('repeater_select_init') == 'ok'){
				return;
			}
			
			$this.data('repeater_select_init', 'ok')
			
			var selected = '';
			var target = '';
			var field = '';
			var labels = '';
			var add_empty = 0;
			$('select option', $this).each(function(){

				if( $(this).html() == 'selected' ){
					selected = $(this).attr('value');
				}
				
				if( $(this).html() == 'target' ){
					target = $(this).attr('value');
				}
				
				if( $(this).html() == 'field' ){
					field = $(this).attr('value');
				}
				
				if( $(this).html() == 'labels' ){
					labels = $(this).attr('value');
				}
				
				if( $(this).html() == 'add_empty' ){
					add_empty = 1;
				}

			});

			if (labels == ''){
				labels = field;
			}
			
			var $select = $('select', $this);
			$select.data('html', $select.html()).html('');
			
			if (add_empty == 1){
				$select.append('<option value="">-- not specified --</option>');
			}
			
			// create contents
			$('.admin_repeater_container_' + target + ' .admin_repeater_block').each(function(){
				
				// find label
				var label = '';
				$('input,textarea', $(this)).each(function(){
					if ($(this).attr('name') == 'panel_params[' + target + '][' + labels + '][]'){
						label = $(this).val();
					}
				});
				
				// find value and add to select
				$('input,textarea', $(this)).each(function(){
					if ($(this).attr('name') == 'panel_params[' + target + '][' + field + '][]'){
						$select.append('<option value="' + $(this).val() + '" ' + (selected == $(this).val() ? ' selected="selected"' : '') + 
								'>' + label + '</option>');
					}
				});

			});
			
		});
	}, 300);
}

$(document).ready(function() {
	
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
		if (typeof admin_input_file_init == 'function'){
			admin_input_file_init();
		}
		
		// init repeater selects
		if (block_html.indexOf('repeater_select') > -1){
			init_admin_repeater_select();
		}
		
		$('body').height('auto');
		
	})
	
	init_admin_repeater_block_delete();
	init_admin_repeater_select();
	
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
