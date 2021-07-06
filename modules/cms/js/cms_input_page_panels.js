function cms_input_page_panels_init(){
	
	$('.cms_list_sortable').sortable().disableSelection();
	
	// save before adding a new panel
	$('.cms_input_page_panels_add').on('click.cms', function(){
		
		var $this = $(this);
		
		// var page_id = $this.data('page_id')
		var parent_id = $this.data('parent_id')

		var cms_page_id = $('.cms_page_id').val();
		var cms_page_panel_id = $('.cms_page_panel_id').val();

		if ($('.cms_page_panel_id').length == 0 && cms_page_id == 0){

			// if no block id field, then must be on the page admin
			
			// ask are you sure
			get_ajax_panel('cms/cms_popup_yes_no', {
				'text': 'The page is not saved.<br>Save this page?'
			}, function(data){
				panels_display_popup(data.result._html, {
					'yes': function(){
						
						cms_page_save({
							'success': function(data){

								$this.data('page_id', data.result.cms_page_id)
								
								// open new page panel selection
								cms_input_page_panel_selector('page', $this.data('page_id'))

							}
						});

					}
				}); 
			});
				
		} else if ($(this).data('parent_id')){
		
			if ($('.cms_page_panel_id').val() == 0){
				
							// is on block admin, but block doesn't have id
				
				// ask are you sure
				get_ajax_panel('cms/cms_popup_yes_no', {
					'text': 'Page panel is not saved. Save the panel?'
				}, function(data){
					panels_display_popup(data.result._html, {
						'yes': function(){
							
							cms_page_panel_save({
								'success':function(data){
									
									window.location.href = config_url + 'admin/cms_page_panel/' + data.result.cms_page_panel_id + '/'
									
									// cms_input_page_panel_selector('panel', $this.data('parent_id'), $this.data('name'), 
									// 		$this.closest('.cms_input_page_panel').data('panels'))
											
									// window.location.href = config_url + 'admin/cms_page_panel/0/0/' + data.result.cms_page_panel_id + '/' + 
									// 			$this.data('name') + '/';
								
								}
							});
	
						}
					}); 
				});

				
			} else {
				cms_input_page_panel_selector('panel', $('.cms_page_panel_id').val(),
						$this.data('name'), $this.closest('.cms_input_page_panels').data('panels'))
			}
			
			
			
		} else if (cms_page_id && cms_page_id != 0){
			console.log('3')
				
			cms_input_page_panel_selector('page', cms_page_id)
			
		} else {
		
			console.log('4')
			
			window.location.href = $this.data('target');

		}
		
	});
	
}

function cms_input_page_panel_selector(target_type, target_id, target_name, filter_panels, return_promise){

	if (!return_promise){
		return_promise = false
	}

	return new Promise(resolve => {
	
		get_ajax_panel('cms/cms_panel_selector', {
			
			'target_type':target_type,
			'target_id':target_id,
			'target_name':target_name,
			'filter_panels':filter_panels
		
		}, function(data){
	
			panels_display_popup(data.result._html, {
				'select': function(callback){
					
					// if shortcut
					if (target_type == 'page' && $('.cms_panel_selector_shortcut_select').val() !== ''){
						
						var shortcut_target_id = $('.cms_panel_selector_shortcut_select').val()
						
						get_ajax('cms/cms_page_panel_operations', {
							'do': 'cms_page_panel_shortcut',
							'cms_page_id': target_id,
							'cms_page_panel_id': shortcut_target_id,
							'success': function(){
								cms_notification('Shortcut created', 3)
								setTimeout(() => window.location.href = config_url + 'admin/page/' + $('.cms_page_id').val() + '/', 1000)
							}
						})
						
						$('.cms_popup_container').remove()
						
						return
						
					}
					
					// if on page
					if(target_type == 'page'){
					
						$('body').append('<form class="cms_params_form" action="' + config_url + 'admin/cms_page_panel/0/" method="post"></form>')
						
						$('.cms_params_form').append('<input name="target_type" value="' + target_type + '">')
						$('.cms_params_form').append('<input name="target_id" value="' + target_id + '">')
						$('.cms_params_form').append('<input name="panel_name" value="' + $('.cms_panel_selector_item_selected').data('panel_name') + '">')
						
						// open cms page panel editor with this panel
						$('.cms_params_form').submit()
						
						return
					
					}
					
					if (return_promise){
					
						// collect data
						var result = {
							'panel_name': $('.cms_panel_selector_item_selected').data('panel_name'),
							'target_type': target_type,
							'parent_id': target_id,
							'parent_field_name': target_name,
						}

						$('.cms_popup_container').remove()
						
						resolve(result)
						
						return
						
					}
					
					// if on panel
					
					$('body').append('<form class="cms_params_form" action="' + config_url + 'admin/cms_page_panel/0/" method="post"></form>')
					
					$('.cms_params_form').append('<input name="target_type" value="' + target_type + '">')
					$('.cms_params_form').append('<input name="target_id" value="' + target_id + '">')
					$('.cms_params_form').append('<input name="target_input_name" value="' + target_name + '">')
					$('.cms_params_form').append('<input name="panel_name" value="' + $('.cms_panel_selector_item_selected').data('panel_name') + '">')
					
					// open cms page panel editor with this panel
					$('.cms_params_form').submit()
	
				},
				'cancel': function(data){
					$('.cms_popup_container').remove()
				}
			}); 			
		})
		
	})
	
}

function cms_input_page_panels_resize(){
		
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_input_page_panels_resize();
	});

	cms_input_page_panels_init();

	cms_input_page_panels_resize();
	
});




