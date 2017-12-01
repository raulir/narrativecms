function cms_input_repeater_select_reinit(){
	
	$('.cms_input_repeater_select_ok').removeClass('cms_input_repeater_select_ok');
	
	cms_input_repeater_select_init();
	
}

function cms_input_repeater_select_init(){
	
	setTimeout(function(){
		
		$('.cms_input_repeater_select').each( function(){
						
			var $this = $(this);
			
			if ($this.hasClass('cms_input_repeater_select_ok')){
				return;
			}
			
			$this.addClass('cms_input_repeater_select_ok')
			
			var selected = $this.data('selected');
			var target = $this.data('target');
			var field = $this.data('field');
			var labels = $this.data('labels');
			var add_empty = parseInt($this.data('add_empty'));

			if ($this.data('labels') == ''){
				labels = $this.data('field');
			}
			
			var $select = $('select', $this);

			$select.html('');
			if (add_empty == 1){
				$select.append('<option value="">-- not specified --</option>');
			}
			
			$('.admin_repeater_container_' + target).addClass('cms_repeater_target');
			
			// create contents
			$('.admin_repeater_container_' + target + ' .cms_repeater_block').each(function(){
				
				// find label
				var label = '';
				$('input,textarea', $(this)).each(function(){
					if ($(this).attr('name') == 'panel_params[' + target + '][' + labels + '][]'){
						label = $(this).val();
					}
				});
				
				// find value and add to select
				$('input,textarea', $(this)).each(function(){
					
					// if called from content change, check if needed to update old selected value to new
					var $cms_input = $(this).closest('.cms_input_text,.cms_input_textarea');

					if ($cms_input.length && $cms_input.data('old_value') && $cms_input.data('old_value') == selected){
						$this.data('selected', $(this).val());
						selected = $(this).val();
					}
					
					if ($(this).attr('name') == 'panel_params[' + target + '][' + field + '][]'){
						$select.append('<option value="' + $(this).val() + '" ' + (selected == $(this).val() ? ' selected="selected"' : '') + '>' + label + '</option>');
					}
				});

			});
			
			// update selected value data for repeater select, as html content gets reset when rebuilding
			if (!$select.hasClass('cms_repeater_select_select_change_ok')){
				$select.addClass('cms_repeater_select_select_change_ok');
				$select.on('change.cms', function(){
					$(this).closest('.cms_input_repeater_select').data('selected', $(this).val());
				})
			}
			
		});
		
		if (typeof cms_input_text_init !== 'undefined'){
			cms_input_text_init();
		}
		
		if (typeof cms_input_textarea_init !== 'undefined'){
			cms_input_textarea_init();
		}
		
	}, 300);
	
}

function cms_input_repeater_select_resize(){
	
}

function cms_input_repeater_select_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_input_repeater_select_resize();
	});
	
	$(window).on('scroll.cms', function(){
		cms_input_repeater_select_scroll();
	});
	
	cms_input_repeater_select_init();

	cms_input_repeater_select_resize();
	
	cms_input_repeater_select_scroll();
	
});
