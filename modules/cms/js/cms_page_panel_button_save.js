function cms_page_panel_save(params){
	
	params = $.extend({'no_mandatory_check':false}, params);
	
	if ($('.cms_page_panel_panel_name').val() != ''){
		
		if (typeof tinyMCE !== 'undefined'){
			tinyMCE.triggerSave();
		}
		
		// check if all mandatory data exists
		var mandatory_extra = '';
		if (!params.no_mandatory_check){
			var mandatory_result = cms_page_panel_check_mandatory('orange');
			if (mandatory_result.length){
				var mandatory_extra = cms_page_panel_format_mandatory(mandatory_result, 'orange');
			}
		}
		
		var data_to_submit = serialize_form('.admin_form')
		
		// add do and language
		data_to_submit['do'] = 'cms_page_panel_save';
		data_to_submit['language'] = $('.cms_language_select_current').data('language');
		
		if (params && params.success){
			
			$.extend(data_to_submit, {
				'success': function(data){
					params.success(data);
				}
			});
			
		} else {
			
			$.extend(data_to_submit, {
				'success': function(data){

					if ($('.cms_page_panel_mode').val() == 'panel_settings'){
							
						// panel settings
						cms_notification('Settings saved' + mandatory_extra, 3);
						
						if ($('#block_id').val() == '0'){
							location.reload();
						} 
						
					} else if ($('.cms_page_panel_id').val() == '0' && (parseInt(data.result.parent_id) > 0)){
					
						// adding child
						cms_notification('Panel created' + mandatory_extra, 3);
						window.location.href = config_url + 'admin/cms_page_panel/' + data.result.cms_page_panel_id + '/';
					
					} else if ($('.cms_page_panel_id').val() == '0' && (parseInt(data.result.cms_page_panel_id) > 0)){
						
						// adding list item
						cms_notification('New ' + data.result.panel_name + ' created', 3);
						window.location.href = config_url + 'admin/cms_page_panel/' + data.result.cms_page_panel_id + '/';
						
					} else {
					
						cms_notification('Panel saved' + mandatory_extra, 3);
					
					}
					
				}
			});
			
		}
		
		get_ajax('cms/cms_page_panel_operations', data_to_submit);
		
	} else {
		
		cms_error('No panel type!', 5);
		
	}
	
}

function cms_page_panel_button_save_init(){
	
	$('.cms_page_panel_save').on('click.r', function(event){
		event.stopPropagation();
		cms_page_panel_save();
	});
	
	$(window).on('keydown.cms', function(event) {
		
		if (event.ctrlKey || event.metaKey) {
			$('.cms_ctrl_hint').addClass('cms_ctrl_hint_active');
		}
		
	    if (event.ctrlKey || event.metaKey) {
	        switch (String.fromCharCode(event.which).toLowerCase()) {
		        case 's':
		            event.preventDefault();
		            $('.cms_page_panel_save').click();
		        break;
	        }
	    }

	});

	$(window).on('keyup.cms', function(event) {

		if (event.ctrlKey || event.metaKey || event.which == 17) {
			$('.cms_ctrl_hint_active').removeClass('cms_ctrl_hint_active');
		}

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
