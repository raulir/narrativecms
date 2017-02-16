function cms_page_panel_button_save_init(){
	
	$('.cms_page_panel_save').on('click.r', function(event){
		event.stopPropagation();
		// if shortcut, go back to page page
		if ($('.admin_block_shortcut_to').length && $('.admin_block_shortcut_to').val() != ''){
			get_ajax('cms_page_panel_operations', {
				'do': 'cms_page_panel_shortcut',
				'cms_page_id': $('[name="page_id"]').val(),
				'cms_page_panel_id': $('.admin_block_shortcut_to').val(),
				'success': function(){
					cms_notification('Shortcut created', 3);
					window.location.href = config_url + 'admin/page/' + $('[name="page_id"]').val() + '/';
				}
			});
		} else if ($('.admin_block_panel_name').val() != ''){
			
			if (typeof tinyMCE !== 'undefined'){
				tinyMCE.triggerSave();
			}
			
			var data = $('.admin_form').serializeArray();
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
			
			get_ajax('cms_page_panel_operations', 
				$.extend(data_to_submit, {
					'success': function(data){
						if ($('.admin_block_shortcut_to').length){
							
							cms_notification('Panel created', 3);
							window.location.href = config_url + 'admin/block/' + data.result.block_id + '/';
							
						} else {
							
							if ($('.cms_page_panel_mode').val() == 'panel_settings'){
								
								// panel settings
								cms_notification('Settings saved', 3);
								
								if ($('#block_id').val() == '0'){
									location.reload();
								} 
								
							} else if ($('#block_id').val() == '0' && (parseInt(data.result.parent_id) > 0)){
							
								// adding child
								cms_notification('Panel created', 3);
								window.location.href = config_url + 'admin/block/' + data.result.block_id + '/' + data.result.parent_id + 
										'/' + $('.cms_page_panel_parent_name').val() + '/';
							
							} else if ($('#block_id').val() == '0' && (parseInt(data.result.block_id) > 0)){
								
								// adding list item
								cms_notification('New ' + data.result.panel_name + ' created', 3);
								window.location.href = config_url + 'admin/cms_list_item/' + data.result.panel_name + '/' + data.result.block_id + '/';
								
							} else {
							
								cms_notification('Panel saved', 3);
							
							}
							
						}
					}
				})
			);
		} else {
			cms_error('Please select panel type or shortcut target!', 5);
		}
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
