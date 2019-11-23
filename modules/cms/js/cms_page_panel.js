
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
			
			if (!$('select', $this).val()){
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
		mandatory_extra = '<br><div style="display: inline-block; color: ' + colour + '; font-size: 14px; padding-top: 10px; ">Missing mandatory values:';
		$.each(mandatory_result, function(key, value){
			mandatory_extra = mandatory_extra + '<br>- ' + value;
		});
		mandatory_extra = mandatory_extra + '</div>';
	}
	
	return mandatory_extra;

}

function cms_page_panel_set_title(title, after){
	
	if (title == '-- select block type --' || title == '-- shortcut to --'){
		after('New block');
		return;
	}

	title = title.replace(/ \(([0-9]*)\)$/g, '');

	get_ajax('cms_page_panel_operations', {
		'do': 'cms_page_panel_title',
		'title': title,
		'success': function(data){
			after(data.result.title);
		}
	});

}

function cms_page_panel_init(){

	var $title = $('input', '.cms_page_panel_title');
	if ($title.val() == 'New block'){
		$title.data('new_block', true);
	}
	
	$('.admin_block_shortcut_to').on('change.cms', function(){
		
		$('.cms_page_panel_panel_name').val('');
	
		if ($title.data('new_block')){
			
			cms_page_panel_set_title($('option:selected', this).text().split('>').pop().trim(), function(title){
				$title.val(title);
			});

		}

	})
	
	$('.cms_page_panel_panel_name').on('change.cms', function(){
		
		$('.admin_block_shortcut_to').val('');
		
		if ($title.data('new_block')){
			
			cms_page_panel_set_title($('option:selected', this).text().split('/').pop().trim(), function(title){
				$title.val(title);
			});
			
		}
	
	})

	$('.cms_repeater_area').sortable().disableSelection();
	
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
	
}

$(document).ready(function() {
	
	cms_page_panel_init();

});
