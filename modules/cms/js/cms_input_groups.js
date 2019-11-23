function cms_input_groups_init(){

	$('.cms_input_groups').each(function(){
		
		var $this = $(this);
		
		if($this.hasClass('cms_input_groups_init')){
			return;
		}
		
		$this.addClass('cms_input_groups_init');
		
		var value = $('.cms_input_groups_value_selected', $this).data('value');
		
		cms_input_groups_set($this, value);
		
		$('.cms_input_groups_value', $this).on('click.cms', function(){
			
			$('.cms_input_groups_input', $this).val($(this).data('value'));
			
			$('.cms_input_groups_value_selected', $this).removeClass('cms_input_groups_value_selected');
			$(this).addClass('cms_input_groups_value_selected');
			
			cms_input_groups_set($this, $(this).data('value'));
			
		});

	});
	
}

function cms_input_groups_set($this, value){
	
	// init all neighbouring inputs
	$('.cms_input_container', $this.closest('.cms_repeater_block')).each(function(){
		
		if ($(this).hasClass('cms_input_container_groups')){
			var groups = $(this).data('groups').split(',');
			if (groups.indexOf(value) > -1){
				$(this).removeClass('cms_input_groups_hidden');
			} else {
				$(this).addClass('cms_input_groups_hidden');
			}
		}
	});
	
}

$(document).ready(function() {
		
	cms_input_groups_init();
	
});
