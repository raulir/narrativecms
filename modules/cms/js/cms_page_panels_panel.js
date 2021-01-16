function cms_page_panels_panel_hide(cms_page_panel_id, new_state, after){
	
	var action = () => {
		get_ajax_panel('cms/cms_page_panel_operations', {
			'cms_page_panel_id': cms_page_panel_id,
			'do': 'cms_page_panel_show'
		}, function(data){
			
			var message = ''
			
			if (data.result.message){
				message = message + data.result.notification
			}
			
			after(data.result.show)
			
			if (data.result.show == 1){
				cms_notification('Child panel published' + message, 3)
			} else {
				cms_notification('Child panel unpublished' + message, 3)
			}
		});
		
	}
	
	action()

	/* // removed mandatory check and display confirmation
	if (new_state){

		// check if all mandatory is filled in
		if (typeof cms_page_panel_check_mandatory == 'function'){
			var mandatory_result = cms_page_panel_check_mandatory('red');
		} else {
			var mandatory_result = [];
		}
		
		if (mandatory_result.length){

			var mandatory_extra = cms_page_panel_format_mandatory(mandatory_result, 'red');
			cms_notification('Error showing panel' + mandatory_extra, 3, 'error')

		} else {

			// ask are you sure
			get_ajax_panel('cms/cms_popup_yes_no', {}, function(data){
				panels_display_popup(data.result._html, {
					'yes': function(){
						
						// if save button, save 
						if ($('.cms_page_panel_save').length){
							
							cms_page_panel_save({
								'no_mandatory_check': true,
								'success':function(data){
									action($this);
								}
							})
						
						} else {
							action($this)
						}
						
					}
				}); 
			});

		}
	} else {
		action()
	}
*/
}

function cms_page_panels_panel_init(){
	
	$('.cms_page_panels_panel_hide').each(function(){
		
		var $this = $(this)
		
		if ($this.hasClass('cms_page_panels_panel_hide_ok')) return
		
		$this.addClass('cms_page_panels_panel_hide_ok')
		
		$this.on('click.cms', function(){
			
			var $container = $this.closest('.cms_page_panels_panel_container')
			
			cms_page_panels_panel_hide($container.data('cms_page_panel_id'), !$container.hasClass('cms_page_panels_panel_hidden'), function(result){
				
				if (result){
					$container.removeClass('cms_page_panels_panel_hidden')
					$this.html('Hide')
				} else {
					$container.addClass('cms_page_panels_panel_hidden')
					$this.html('Show')
				}
				
			})
			
			
		})
	
	})
	
	// $('.cms_page_panels_panel_container').sortable().disableSelection()
	
	/*
	
	// save before adding a new panel
	$('.cms_page_panels_panel_add').on('click.cms', function(){
		
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
						$this.data('name'), $this.closest('.cms_page_panels_panel').data('panels'))
			}
			
			
			
		} else if (cms_page_id && cms_page_id != 0){
			console.log('3')
				
			cms_input_page_panel_selector('page', cms_page_id)
			
		} else {
		
			console.log('4')
			
			window.location.href = $this.data('target');

		}
		
	});
	
	*/
	
}

function cms_input_page_panel_selector(target_type, target_id, target_name, filter_panels){
	
	get_ajax_panel('cms/cms_panel_selector', {
		
		'target_type':target_type,
		'target_id':target_id,
		'target_name':target_name,
		'filter_panels':filter_panels
	
	}, function(data){

		panels_display_popup(data.result._html, {
			'select': function(data){
				
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
	});
	
}

function cms_page_panels_panel_resize(){
		
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panels_panel_resize();
	});

	cms_page_panels_panel_init();

	cms_page_panels_panel_resize();
	
});




