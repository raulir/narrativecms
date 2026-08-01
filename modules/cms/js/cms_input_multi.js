function cms_input_multi_sticky_keys($input){

	var raw = String($input.attr('data-sticky') || '')
	if (raw === ''){
		return []
	}
	return raw.split(',').map(function(s){
		return String(s)
	}).filter(function(s){
		return s !== ''
	})

}

function cms_input_multi_is_sticky($input, value){

	var keys = cms_input_multi_sticky_keys($input)
	return keys.indexOf(String(value)) !== -1

}

function cms_input_multi_init($root){

	var $scope = $root ? $root.find('.cms_input_multi') : $('.cms_input_multi');

	$scope.not('.cms_input_multi_ok').each(function(){

		var $input = $(this);

		$input.addClass('cms_input_multi_ok');

		$('.cms_input_multi_add', $input).on('click.cms', function(){

			var value = $('.cms_input_multi_select', $input).val();

			if (value == null){
				return
			}
			if (cms_input_multi_is_sticky($input, value)){
				return
			}

			// Scope to this multi field (global selector can hit wrong field / re-add dupes)
			var $values = $('.cms_input_multi_values', $input)
			var already = false
			$values.find('input[type="hidden"]').each(function(){
				if (String($(this).val()) === String(value)){
					already = true
				}
			})
			if (already){
				return
			}

			$values.append(
					'<div class="cms_input_multi_item"><input type="hidden" name="' + $input.data('name') + '[]" value="' + value + '">' +
					'<div class="cms_input_multi_item_label">' + $('.cms_input_multi_select option:selected', $input).html() + '</div></div>');

			$('.cms_input_multi_select option:selected', $input).remove();

			cms_input_multi_item_init($input);

		});

		cms_input_multi_item_init($input);

		// Sticky chips stay first and are not sortable
		$('.cms_input_multi_values', $input).sortable({
			items: '> .cms_input_multi_item:not(.cms_input_multi_item_sticky)',
			cancel: '.cms_input_multi_item_sticky'
		});

	});

}

function cms_input_multi_item_init($input){

	$('.cms_input_multi_item', $input).each(function(){
		var $item = $(this)
		if ($item.hasClass('cms_input_multi_item_sticky')){
			$item.css({'background-image': 'none'})
			$item.off('click.cms')
			return
		}
		$item.css({'background-image': 'url(' + $input.data('bg') + ')'})
	})

	$('.cms_input_multi_item:not(.cms_input_multi_item_sticky)', $input).off('click.cms').on('click.cms', function(){
		var val = $('input', $(this)).val()
		if (cms_input_multi_is_sticky($input, val)){
			return
		}
		$('.cms_input_multi_select', $input).append('<option value="' + val + '">' +
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
