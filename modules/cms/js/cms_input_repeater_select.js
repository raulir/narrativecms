function cms_input_repeater_select_init(){
	
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
