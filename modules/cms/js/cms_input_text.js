function cms_input_text_init(){
	
	$('.cms_input_text').each(function(){
		
		var $this = $(this);
		
		if ($this.hasClass('cms_input_text_ok')){
			return;
		}
		
		if ($this.closest('.cms_repeater_target').length){

			$this.addClass('cms_input_text_ok');
			
			$('input', $this).on('focus.cms', function(){
				$this.data('old_value', $(this).val());
			});
			
			$('input', $this).on('change.cms', function(){
				cms_input_repeater_select_reinit();
			});
			
		}
		
		$('.cms_input_text_default', $this).on('click.cms', function(){
			$('.cms_input_text_input', $this).val($(this).data('value'))
		})
		
	})

}

function cms_input_text_resize(){
	
}

function cms_input_text_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_input_text_resize();
	});
	
	$(window).on('scroll.cms', function(){
		cms_input_text_scroll();
	});
	
	cms_input_text_init();

	cms_input_text_resize();
	
	cms_input_text_scroll();

});
