function cms_input_multi_init($root){

	var $scope = $root ? $root.find('.cms_input_multi') : $('.cms_input_multi');

	$scope.not('.cms_input_multi_ok').each(function(){

		var $input = $(this);

		$input.addClass('cms_input_multi_ok');
		
		$('.cms_input_multi_add', $input).on('click.cms', function(){
			
			var value = $('.cms_input_multi_select', $input).val();
			
			if (value != null){
				
				$('.cms_input_multi_values').append(
						'<div class="cms_input_multi_item"><input type="hidden" name="' + $input.data('name') + '[]" value="' + value + '">' +
						'<div class="cms_input_multi_item_label">' + $('.cms_input_multi_select option:selected', $input).html() + '</div></div>');
				
				$('.cms_input_multi_select option:selected', $input).remove();

				cms_input_multi_item_init($input);
				
			}
		});
		
		cms_input_multi_item_init($input);
		
		$('.cms_input_multi_values', $input).sortable();
		
	});
	
}

function cms_input_multi_item_init($input){

	$('.cms_input_multi_item', $input).css({'background-image': 'url(' + $input.data('bg') + ')'});
	
	$('.cms_input_multi_item', $input).off('click.cms').on('click.cms', function(){
		$('.cms_input_multi_select', $input).append('<option value="' + $('input', $(this)).val() + '">' + 
				$('.cms_input_multi_item_label', $(this)).html() + '</option>');
		$(this).remove();
	});
	
}

function cms_input_multi_resize(){
	
}

function cms_input_multi_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_input_multi_resize();
	});
	
	$(window).on('scroll.cms', function(){
		cms_input_multi_scroll();
	});
	
	cms_input_multi_init();

	cms_input_multi_resize();
	
	cms_input_multi_scroll();

});
