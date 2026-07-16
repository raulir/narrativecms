function cms_input_page_panels_find_page_field(page_id){

	var $field = $('.cms_input_page_panels_add').filter(function(){
		return String($(this).attr('data-page_id') || $(this).data('page_id') || '') === String(page_id || '')
	}).closest('.cms_input_page_panels')

	if ($field.length){
		return $field.first()
	}

	// Page editor: single main panels list
	return $('.cms_page_container .cms_input_page_panels').first()

}

function cms_input_page_panels_ensure_list($field){

	var $list = $field.find('ul.cms_input_page_panels_list')
	if ($list.length){
		return $list
	}

	$field.find('.cms_input_page_panels_message').remove()

	var list_class = $field.attr('data-sortable_class') || 'cms_list_sortable'
	$list = $('<ul class="' + list_class + ' cms_input_page_panels_list"></ul>')
	$field.find('.cms_input_page_panels_add').before($list)

	// Make new list sortable (existing lists already inited by page/panel JS)
	if (!$list.hasClass('ui-sortable')){
		if (list_class.indexOf('cms_page_sortable') !== -1){
			$list.sortable({
				'stop': function(){
					var block_orders = {}
					$list.find('.block_id').each(function(index){
						block_orders[$(this).val()] = index + 1
					})
					get_ajax('cms/cms_page_operations', {
						'do': 'cms_page_panel_order',
						'orders': block_orders,
						'cms_page_id': $('.cms_page_id').val()
					})
				}
			}).disableSelection()
		} else {
			$list.sortable().disableSelection()
		}
	}

	return $list

}

function cms_input_page_panels_build_shortcut_row(shortcut, input_name){

	var id = shortcut.cms_page_panel_id
	var title = shortcut.title || '[ no title ]'
	var show = parseInt(shortcut.show, 10) === 1
	var goto_id = shortcut.goto_id || ''
	var hidden_class = show ? '' : ' cms_item_hidden'

	var input_html = input_name
		? '<input type="hidden" name="' + input_name + '[]" value="' + id + '">'
		: '<input type="hidden" class="block_id" value="' + id + '">'

	var goto_html = goto_id
		? '<a class="cms_small_button" href="' + _cms_base + 'admin/cms_page_panel/' + goto_id + '/">goto</a>'
		: ''

	// Drag icon: copy background from an existing row if present
	var style = ''
	var $sample = $('.cms_input_page_panels_item').first()
	if ($sample.length){
		var bg = $sample.css('background-image')
		if (bg && bg !== 'none'){
			style = ' style="background-image: ' + bg + '; background-repeat: no-repeat; background-position: 0.6rem 0.6rem; background-size: 1.4rem auto;"'
		}
	}

	return $(
		'<li class="cms_list_sortable_item cms_input_page_panels_item' + hidden_class + '"' + style + '>' +
			input_html +
			'<div class="admin_text cms_input_page_panels_item_heading"></div>' +
			'<div class="cms_input_page_panels_item_buttons">' +
				'<div class="cms_small_button cms_page_panel_delete" data-cms_page_panel_id="' + id + '">remove</div>' +
				'<div class="cms_small_button cms_page_panel_show" data-cms_page_panel_id="' + id + '">' +
					(show ? 'hide' : 'show') +
				'</div>' +
				goto_html +
			'</div>' +
		'</li>'
	).find('.cms_input_page_panels_item_heading').text(title).end()

}

function cms_input_page_panels_inject_shortcut(page_id, shortcut){

	if (!shortcut || !shortcut.cms_page_panel_id){
		return false
	}

	var $field = cms_input_page_panels_find_page_field(page_id)
	if (!$field.length){
		return false
	}

	var $list = cms_input_page_panels_ensure_list($field)
	var input_name = $field.attr('data-input_name') || ''
	var $row = cms_input_page_panels_build_shortcut_row(shortcut, input_name)

	$list.append($row)

	if (typeof cms_page_panel_button_show_activate === 'function'){
		cms_page_panel_button_show_activate()
	}

	if (typeof cms_preview_reload === 'function'){
		cms_preview_reload()
	}

	return true

}

function cms_input_page_panels_after_remove($li){

	var $list = $li.closest('ul.cms_input_page_panels_list')
	var $field = $li.closest('.cms_input_page_panels')

	$li.remove()

	if ($list.length && !$list.children('li').length){
		$list.replaceWith('<div class="cms_input_page_panels_message">No panels added</div>')
	}

	if (typeof cms_preview_reload === 'function' && $field.closest('.cms_page_container').length){
		cms_preview_reload()
	}

}

function cms_input_page_panels_bind_delete($panel){

	$panel.off('click.cms_pp_delete', '.cms_page_panel_delete')
		.on('click.cms_pp_delete', '.cms_page_panel_delete', function(){

			var $this = $(this)
			var cms_page_panel_id = $this.attr('data-cms_page_panel_id') || $this.data('cms_page_panel_id')

			get_ajax_panel('cms/cms_popup_yes_no', {
				'text': 'Delete block shortcut?'
			}, function(data){
				panels_display_popup(data.result._html, {
					'yes': function(){
						get_ajax('cms/cms_page_panel', {
							'cms_page_panel_id': cms_page_panel_id,
							'do': 'cms_page_panel_delete',
							'success': function(){
								cms_input_page_panels_after_remove($this.closest('li'))
								cms_notification('Shortcut removed', 3)
							}
						})
					}
				})
			})

		})

}

function cms_input_page_panels_init($root){

	var $scope = $root ? $root.find('.cms_input_page_panels') : $('.cms_input_page_panels');

	$scope.not('.cms_input_page_panels_ok').each(function(){

		var $panel = $(this);

		$panel.addClass('cms_input_page_panels_ok');

		$('.cms_list_sortable', $panel).sortable().disableSelection();

		cms_input_page_panels_bind_delete($panel)

		// save before adding a new panel
		$('.cms_input_page_panels_add', $panel).on('click.cms', function(){
		
		var $this = $(this);
		
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
								$this.attr('data-page_id', data.result.cms_page_id)
								
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
									
									window.location.href = _cms_base + 'admin/cms_page_panel/' + data.result.cms_page_panel_id + '/'
								
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
				
			cms_input_page_panel_selector('page', cms_page_id)
			
		} else {
		
			window.location.href = $this.data('target');

		}

		});

	});

}

function cms_input_page_panel_selector(target_type, target_id, target_name, filter_panels){
	
	get_ajax_panel('cms/cms_panel_selector', {
		
		'target_type':target_type,
		'target_id':target_id,
		'target_name':target_name,
		'filter_panels':filter_panels
	
	}, function(data){

		panels_display_popup(data.result._html, {
			'select': function(after){

				// Select stays opacity-disabled until a choice is made; ignore accidental clicks
				if ($('.cms_panel_selector_select').hasClass('cms_panel_selector_select_disabled')){
					return
				}
				
				// if shortcut
				if (target_type == 'page' && $('.cms_panel_selector_shortcut_select').val() !== ''){
					
					var shortcut_target_id = $('.cms_panel_selector_shortcut_select').val()
					
					get_ajax('cms/cms_page_panel', {
						'do': 'cms_page_panel_shortcut',
						'cms_page_id': target_id,
						'cms_page_panel_id': shortcut_target_id,
						'success': function(resp){

							var result = (resp && resp.result) ? resp.result : {}
							var shortcut = result.shortcut || null

							if (shortcut && cms_input_page_panels_inject_shortcut(target_id, shortcut)){
								cms_notification('Shortcut created', 3)
							} else if (shortcut){
								// List not found — fall back to hard navigation
								cms_notification('Shortcut created', 3)
								window.location.href = _cms_base + 'admin/page/' + target_id + '/'
							} else {
								cms_notification('Shortcut created but UI did not update', 5, 'error')
							}

						}
					})
					
					if (typeof after === 'function'){
						after()
					}
					
					return
					
				}

				// Use attr(), not .data() — jQuery data() can miss data-panel_name / mis-parse values
				var panel_name = $('.cms_panel_selector_item_selected').attr('data-panel_name') || ''
				if (!panel_name || panel_name.indexOf('/') === -1){
					cms_notification('Select a panel type first', 3)
					return
				}
				
				// if on page
				if(target_type == 'page'){
				
					$('body').append('<form class="cms_params_form" action="' + _cms_base + 'admin/cms_page_panel/0/" method="post"></form>')
					
					$('.cms_params_form').append('<input type="hidden" name="target_type" value="' + target_type + '">')
					$('.cms_params_form').append('<input type="hidden" name="target_id" value="' + target_id + '">')
					$('.cms_params_form').append('<input type="hidden" name="panel_name" value="' + panel_name + '">')
					
					// open cms page panel editor with this panel
					$('.cms_params_form').submit()
					
					return
				
				}
				
				// if on panel
				
				$('body').append('<form class="cms_params_form" action="' + _cms_base + 'admin/cms_page_panel/0/" method="post"></form>')
				
				$('.cms_params_form').append('<input type="hidden" name="target_type" value="' + target_type + '">')
				$('.cms_params_form').append('<input type="hidden" name="target_id" value="' + target_id + '">')
				$('.cms_params_form').append('<input type="hidden" name="target_input_name" value="' + target_name + '">')
				$('.cms_params_form').append('<input type="hidden" name="panel_name" value="' + panel_name + '">')
				
				// open cms page panel editor with this panel
				$('.cms_params_form').submit()

			},
			'cancel': function(data){
				$('.cms_popup_container').remove()
			}
		}); 			
	});
	
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
