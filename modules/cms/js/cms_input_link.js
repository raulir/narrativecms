function cms_input_link_init(){

	$('.cms_input_link_container').not('.cms_input_link_ok').each(function(){
	
		var $container = $(this);
		$container.addClass('cms_input_link_ok');
	
		$('.cms_input_link_target', $container).on('change.cms', function(){
			cms_input_link_target($container);
		});

		$('.cms_input_link_target', $container).each(function(){
			cms_input_link_target($container);
		});
		
		$('.cms_input_link_url_display', $container).on('keyup.cms', function(){
			$(this).siblings('.cms_input_link_url').val($(this).val());
			cms_input_link_target($container);
		});
		
		$('.cms_input_link_select', $container).on('change.cms', function(){
			cms_input_link_target($container);
		});

	});
		
}

function cms_input_link_resize(){
		
}

// if link input changes
function cms_input_link_target($container){
	
	var $select = $('.cms_input_link_target', $container);
	
	var $input_url = $select.siblings('.cms_input_link_url');
	var $input_text = $select.siblings('.cms_input_link_text');
	var $input_target_id = $select.siblings('.cms_input_link_target_id');
	var $input_value = $select.siblings('.cms_input_link_value');
	
	var $input_url_display = $select.siblings('.cms_input_link_url_display');
	
	var $input_selects = $select.siblings('.cms_input_link_select');
	$input_selects.css({'display':'none'});

	if ($select.val() == '_none'){
		
		$input_url_display.val('').attr('disabled', true);
		$input_url.val('');
		$input_text.val('');
		$input_target_id.val('');
		$input_value.val('');
		
	} else if ($select.val() == '_manual'){
		
		$input_url_display.attr('disabled', false);
		$input_target_id.val('');
		$input_value.val($input_url_display.val());
		
	} else if ($select.val() == '_page'){
		
		$input_url_display.attr('disabled', true);
		$input_target_id.val('');

		var $page_select = $('.cms_input_link_select_page', $container).css({'display':''});

		var $option = $page_select.children('option:selected');
		$page_select.siblings('.cms_input_link_url,.cms_input_link_url_display').val($option.data('url'));

		$input_value.val($page_select.val());

	} else { // list
		
		$input_url_display.attr('disabled', true);
		cms_input_link_update_list($select);
		var $list_select = $select.siblings('.cms_input_link_select_' + $select.val());
		
		var list_select_val = $list_select.val()
		if (typeof list_select_val == 'undefined'){
			list_select_val = ''
		}

		$input_value.val(list_select_val.replace('__', '/')/* + '=' + $input_target_id.val()*/);
		
	}
	
}

function cms_input_link_update_list($select){

	var $list_select = $select.siblings('.cms_input_link_select_' + $select.val()).css({'display':''});
	
	$list_select.off('change.cms').on('change.cms', function(){
		var $this = $(this);
		var $option = $this.children('option:selected');
		$this.siblings('.cms_input_link_url_display').val($option.data('slug'));
		$this.siblings('.cms_input_link_url').val($this.val());
		$this.siblings('.cms_input_link_target_id').val($option.data('target_id'));
		$this.siblings('.cms_input_link_value').val($this.val());
	}).change();

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_input_link_resize();
	});

	cms_input_link_init();

	cms_input_link_resize();
	
});




