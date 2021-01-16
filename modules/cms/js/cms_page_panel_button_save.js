function cms_page_panel_serialise_selector($set){

	var data = $set.serializeArray();
	var data_to_submit = {};
	$.each(data, function(key, value){
		var re = value.name.slice(-2);
		if (re == '[]') {
			var name = value.name.replace('[]', '');
			if(typeof data_to_submit[name] == 'undefined' || !$.isArray(data_to_submit[name])){
				data_to_submit[name] = [value.value];
			} else {
				data_to_submit[name].push(value.value);
			}
		} else {
			data_to_submit[value.name] = value.value;
		}
	});
	
	return data_to_submit

}

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
		
		var $admin_form = $('.admin_form')
		var $panels_inputs = $('.cms_input_page_panels_inline_container', $admin_form)
		
		var datasets = []
		
		$panels_inputs.each(function(){
			
			var $child_panels = $('.cms_page_panels_panel_container', $(this))
			var sort = 0;
			
			$child_panels.each(function(){
				
				var dataset = cms_page_panel_serialise_selector($('input, select, textarea', $(this)))
				
				dataset.cms_page_id = '0'
				dataset.cms_page_panel_id = $(this).data('cms_page_panel_id')
				dataset.panel_name = $(this).data('panel_name')
				dataset.parent_id = $('.cms_page_panel_id').val()
				dataset.sort = sort++
				dataset.title = $('.cms_page_panels_panel_title', $(this)).html()
					
				datasets.push(dataset)
				$('input, select, textarea', $(this)).addClass('cms_page_panel_serialise_ok')
			
			})
		
		})
		
		datasets.push(cms_page_panel_serialise_selector($('input, select, textarea', $admin_form).not('.cms_page_panel_serialise_ok')))

		console.log(datasets)
		
		return
		
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
