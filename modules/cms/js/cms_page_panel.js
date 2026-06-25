
function cms_page_panel_check_mandatory(colour){
	
	var $mandatory = $('.cms_input_mandatory');
	
	var ret = [];
	
	$mandatory.each(function(){
		
		var $this = $(this);
		var label_extra = '';
		$('label', $this).css({'color':''});
		
		// check if inside repeater
		if ($this.closest('.cms_repeater_area').length){
			
			label_extra = $this.closest('.cms_repeater_area').data('label') + ' &gt; ' + ($this.closest('.cms_repeater_block').prevAll('.cms_repeater_block').length + 1) + ': ';
			
		}
		
		var label = (label_extra + $('label', $this).html()).replace(/ \*$/, '');
		
		if ($this.hasClass('cms_input_text') || $this.hasClass('cms_input_date')){
			
			if (!$('input', $this).val()){
				ret.push(label);
				$('label', $this).css({'color':colour});
			}
			
		} else if ($this.hasClass('cms_input_textarea')){
			
			if (!$('textarea', $this).val()){
				ret.push(label);
				$('label', $this).css({'color':colour});
			}
			
		} else if ($this.hasClass('cms_input_image')){
			
			if (!$('.cms_input_image_input', $this).val()){
				ret.push(label);
				$('label', $this).css({'color':colour});
			}
			
		} else if ($this.hasClass('cms_input_select')){ // includes fk and repeater_select
			
			var val = $('select', $this).val()
			
			if (!val || val === '0'){
				ret.push(label);
				$('label', $this).css({'color':colour});
			}
			
		}
		
	})
	
	return ret;
	
}

function cms_page_panel_format_mandatory(mandatory_result, colour){
	
	var mandatory_extra = '';
	
	if (mandatory_result.length){
		mandatory_extra = '<br><div style="display: inline-block; color: ' + colour + '; font-size: 1.4rem; padding-top: 10px; ">Missing mandatory values:';
		$.each(mandatory_result, function(key, value){
			mandatory_extra = mandatory_extra + '<br>- ' + value;
		});
		mandatory_extra = mandatory_extra + '</div>';
	}
	
	return mandatory_extra;

}

var cms_page_panel_title_preview_timer = null
var cms_page_panel_title_preview_extend = 0
var cms_page_panel_title_preview_last_update = 0
var cms_page_panel_title_preview_request_id = 0
var cms_page_panel_title_preview_min_interval = 3000
var cms_page_panel_title_preview_extend_per_change = 1000

function cms_page_panel_update_breadcrumb_title(text){

	var $title = $('.cms_page_panel_toolbar_title')

	if ($title.length){
		$title.html(strip_tags(text))
	}

}

function cms_page_panel_title_preview_enabled(){

	return $('.cms_page_id').val() == '0'
		&& $('input[name="sort"]').val() != '0'
		&& $('.cms_page_panel_panel_name').val() != ''

}

function cms_page_panel_onpage_title_sync_enabled(){

	return parseInt($('.cms_page_id').val()) > 0

}

function cms_page_panel_fetch_title_preview(){

	if (!cms_page_panel_title_preview_enabled()){
		return
	}

	if (typeof tinyMCE !== 'undefined'){
		tinyMCE.triggerSave()
	}

	var request_id = ++cms_page_panel_title_preview_request_id
	var data_to_submit = cms_page_panel_save_serialize_form('.admin_form')

	data_to_submit['do'] = 'cms_page_panel_preview_title'
	data_to_submit['language'] = $('.cms_language_select_current').data('language')

	get_ajax('cms/cms_page_panel_operations', $.extend({}, data_to_submit, {
		success: function(data){
			if (request_id != cms_page_panel_title_preview_request_id){
				return
			}
			if (data.result && data.result.title){
				cms_page_panel_update_breadcrumb_title(data.result.title)
				cms_page_panel_title_preview_last_update = Date.now()
				cms_page_panel_title_preview_extend = 0
			}
		}
	}))

}

function cms_page_panel_schedule_title_preview(){

	if (!cms_page_panel_title_preview_enabled()){
		return
	}

	clearTimeout(cms_page_panel_title_preview_timer)

	var now = Date.now()
	var elapsed = now - cms_page_panel_title_preview_last_update

	if (elapsed >= cms_page_panel_title_preview_min_interval){
		cms_page_panel_title_preview_extend = 0
		cms_page_panel_title_preview_timer = setTimeout(cms_page_panel_fetch_title_preview, 0)
		return
	}

	cms_page_panel_title_preview_extend += cms_page_panel_title_preview_extend_per_change
	var delay = (cms_page_panel_title_preview_min_interval - elapsed) + cms_page_panel_title_preview_extend

	cms_page_panel_title_preview_timer = setTimeout(function(){
		cms_page_panel_title_preview_extend = 0
		cms_page_panel_fetch_title_preview()
	}, delay)

}

function cms_page_panel_title_preview_on_save(data){

	if (cms_page_panel_title_preview_enabled() && data.result && data.result.title){
		cms_page_panel_update_breadcrumb_title(data.result.title)
		cms_page_panel_title_preview_last_update = Date.now()
		cms_page_panel_title_preview_extend = 0
	} else if (cms_page_panel_onpage_title_sync_enabled()){
		cms_page_panel_update_breadcrumb_title($('input', '.cms_page_panel_title').val())
	}

}

function cms_page_panel_title_preview_init(){

	if (cms_page_panel_title_preview_enabled()){

		$('.admin_form').off('input.title_preview change.title_preview')
			.on('input.title_preview change.title_preview', 'input, textarea, select', function(){
				cms_page_panel_schedule_title_preview()
			})

	}

	if (cms_page_panel_onpage_title_sync_enabled()){

		$('input', '.cms_page_panel_title').off('input.title_sync').on('input.title_sync', function(){
			cms_page_panel_update_breadcrumb_title($(this).val())
		})

	}

}

function cms_page_panel_init(){

	var $title = $('input', '.cms_page_panel_title');
	if ($title.val() == 'New block'){
		$title.data('new_block', true);
	}
	
	$('.cms_repeater_area').each(function(){
		
		if ($(this).parent().hasClass('cms_repeater_container_readonly')){
			
		} else {
			$(this).sortable().disableSelection()
		}
		
	})
	
	cms_page_panel_title_preview_init()

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
	
}

$(document).ready(function() {
	
	cms_page_panel_init();

});
