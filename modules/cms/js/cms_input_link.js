function cms_input_link_init(){

	$('.cms_input_link_target').off('change.cms').on('change.cms', function(){
		cms_input_link_target($(this));
	});

	$('.cms_input_link_target').each(function(){
		cms_input_link_target($(this));
	});
	
	$('.cms_input_link_text_display').off('keyup.cms').on('keyup.cms', function(){
		$(this).siblings('.cms_input_link_text').val($(this).val());
	});
	
	$('.cms_input_link_url_display').off('keyup.cms').on('keyup.cms', function(){
		$(this).siblings('.cms_input_link_url').val($(this).val());
	});
	
}

function cms_input_link_resize(){
		
}

function cms_input_link_target($select){
	
	var $input_url = $select.siblings('.cms_input_link_url');
	var $input_text = $select.siblings('.cms_input_link_text');
	var $input_target_id = $select.siblings('.cms_input_link_target_id');
	
	var $input_url_display = $select.siblings('.cms_input_link_url_display');
	var $input_text_display = $select.siblings('.cms_input_link_text_display');
	
	var $input_selects = $select.siblings('.cms_input_link_select');
	$input_selects.css({'display':'none'});
	
	if ($select.val() == '_none'){
		$input_url_display.val('').attr('disabled', true);
		$input_text_display.val('').attr('disabled', true);
		$input_url.val('');
		$input_text.val('');
		$input_target_id.val('');
	} else if ($select.val() == '_manual'){
		$input_url_display.attr('disabled', false);
		$input_text_display.attr('disabled', false);
		$input_target_id.val('');
	} else if ($select.val() == '_page'){
		$input_url_display.attr('disabled', true);
		$input_text_display.attr('disabled', true);
		$input_target_id.val('');
		cms_input_link_update_page($select);
	} else {
		$input_url_display.attr('disabled', true);
		$input_text_display.attr('disabled', true);
		cms_input_link_update_list($select);
	}
	
}

function cms_input_link_update_page($select){

	var $page_select = $select.siblings('.cms_input_link_select_page').css({'display':''});
	
	$page_select.off('change.cms').on('change.cms', function(){
		var $this = $(this);
		var $option = $this.children('option:selected');
		$this.siblings('.cms_input_link_url,.cms_input_link_url_display').val($option.data('url'));
		$this.siblings('.cms_input_link_text,.cms_input_link_text_display').val($option.html());
	}).change();

}

function cms_input_link_update_list($select){

	var $list_select = $select.siblings('.cms_input_link_select_' + $select.val()).css({'display':''});
	
	$list_select.off('change.cms').on('change.cms', function(){
		var $this = $(this);
		var $option = $this.children('option:selected');
		$this.siblings('.cms_input_link_url_display').val($option.data('slug'));
		$this.siblings('.cms_input_link_url').val($this.val());
		$this.siblings('.cms_input_link_text,.cms_input_link_text_display').val($option.html());
		$this.siblings('.cms_input_link_target_id').val($option.data('target_id'));
	}).change();

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_input_link_resize();
	});

	cms_input_link_init();

	cms_input_link_resize();
	
});




