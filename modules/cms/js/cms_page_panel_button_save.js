function cms_page_panel_save_submit(params){
	
	// check if all mandatory data exists
	if (!params.no_mandatory_check){
		var mandatory_result = cms_page_panel_check_mandatory('red');
		if (mandatory_result.length){
			var mandatory_extra = cms_page_panel_format_mandatory(mandatory_result, 'red');
			cms_notification('Cannot save' + mandatory_extra, 3, 'error');
			return;
		}
	}
	
	var data_to_submit = cms_page_panel_save_serialize_form('.admin_form')
	
	// add do and language
	data_to_submit['do'] = 'cms_page_panel_save';
	data_to_submit['language'] = $('.cms_language_select_current').data('language');
	
	if (params && params.success){
		
		$.extend(data_to_submit, {
			'success': function(data){
				cms_page_panel_title_preview_on_save(data)
				params.success(data);
			}
		});
		
	} else {
		
		$.extend(data_to_submit, {
			'success': function(data){

				cms_page_panel_title_preview_on_save(data)

				if ($('.cms_page_panel_mode').val() == 'panel_settings'){
						
					// panel settings
					cms_notification('Settings saved', 3);
					
					if ($('#block_id').val() == '0'){
						location.reload();
					} 
					
				} else if ($('.cms_page_panel_id').val() == '0' && (parseInt(data.result.parent_id) > 0)){
				
					// adding child
					cms_notification('Panel created', 3);
					window.location.href = _cms_base + 'admin/cms_page_panel/' + data.result.cms_page_panel_id + '/';
				
				} else if ($('.cms_page_panel_id').val() == '0' && (parseInt(data.result.cms_page_panel_id) > 0)){
					
					// adding list item
					cms_notification('New ' + data.result.panel_name + ' created', 3);
					window.location.href = _cms_base + 'admin/cms_page_panel/' + data.result.cms_page_panel_id + '/';
					
				} else {
				
					cms_notification('Panel saved', 3);

					if (typeof cms_preview_reload === 'function'){
						cms_preview_reload();
					}
				
				}
				
			}
		});
		
	}
	
	get_ajax('cms/cms_page_panel', data_to_submit);
	
}

function cms_page_panel_save(params){
	
	params = $.extend({'no_mandatory_check':false}, params);
	
	if ($('.cms_page_panel_panel_name').val() != ''){
		
		if (typeof tinyMCE !== 'undefined'){
			tinyMCE.triggerSave();
		}

		cms_page_panel_save_submit(params);
		
	} else {
		
		cms_error('No panel type!', 5);
		
	}
	
}

var cms_page_panel_save_serialize_form = function(form_selector) {

	var result = {}

    $(form_selector).serializeArray().forEach(function(item) {
        var name = item.name
        var value = item.value

        if (!name) return

        if (name.slice(-2) === '[]') {
            var clean_name = name.slice(0, -2)

            if (!Array.isArray(result[clean_name])) {
                result[clean_name] = []
            }
            result[clean_name].push(value)
        } else {
            result[name] = value
        }
    })

    return result
    
}

function cms_page_panel_button_save_init($root){

	var $scope = $root ? $root.find('.cms_page_panel_save') : $('.cms_page_panel_save');

	$scope.not('.cms_page_panel_button_save_ok').each(function(){

		var $button = $(this);

		$button.addClass('cms_page_panel_button_save_ok');

		$button.on('click.cms', function(event){
			event.stopPropagation();
			cms_page_panel_save();
		});

	});

}

function cms_page_panel_button_save_resize(){
		
}

function cms_page_panel_button_save_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panel_button_save_resize();
	});

	$(window).on('scroll.cms', function(){
		cms_page_panel_button_save_scroll();
	});
	
	cms_page_panel_button_save_init();

	cms_page_panel_button_save_resize();
	
	cms_page_panel_button_save_scroll();
	
});
