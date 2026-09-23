function cms_page_panel_show_is_hidden($this){

	if ($this.is('[data-show]')){
		return String($this.attr('data-show')) !== '1'
	}

	var $label = $this.children('.cms_page_panel_show_label')
	var text = $label.length ? $label.text() : $this.text()

	return text.trim() == 'show'

}

function cms_page_panel_show_apply($this, show){

	if (show == 1){
		$this.closest('li').removeClass('cms_item_hidden')
	} else {
		$this.closest('li').addClass('cms_item_hidden')
	}

	if ($this.is('[data-show]')){
		$this.attr('data-show', show == 1 ? '1' : '0')
		$this.toggleClass('cms_list_list_eye_off', show != 1)
		return
	}

	var $label = $this.children('.cms_page_panel_show_label')
	var $text = $label.length ? $label : $this
	$text.html(show == 1 ? 'hide' : 'show')

}

function cms_page_panel_button_show_activate(){
	$('.cms_page_panel_show').off('click.cms').on('click.cms', function(){

		var action = function($this){
			var cms_page_panel_id = $this.data('cms_page_panel_id');
			get_ajax_panel('cms/cms_page_panel', {
				'cms_page_panel_id': cms_page_panel_id,
				'do': 'cms_page_panel_show'
			}, function(data){
				
				var message = ''
				
				if (data.result.message){
					message = message + data.result.notification
				}

				cms_page_panel_show_apply($this, data.result.show)
				
				if (data.result.show == 1){
					cms_notification('Page panel published' + message, 3)
				} else {
					cms_notification('Page panel unpublished' + message, 3)
				}
			});
			
		}
		
		var $this = $(this);

		if (cms_page_panel_show_is_hidden($this)){

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
			action($this);
		}

	});
}


function cms_page_panel_button_show_init($root){

	var $scope = $root ? $root.find('.cms_list_container, .cms_page_container') : $('.cms_list_container, .cms_page_container');

	$scope.not('.cms_page_panel_button_show_ok').each(function(){

		$(this).addClass('cms_page_panel_button_show_ok');

		cms_page_panel_button_show_activate();

	});

	if (!$scope.length){
		if (!$('body').hasClass('cms_page_panel_button_show_ok')){
			$('body').addClass('cms_page_panel_button_show_ok');
			cms_page_panel_button_show_activate();
		}
	}

}

function cms_page_panel_button_show_resize(){
		
}

function cms_page_panel_button_show_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_page_panel_button_show_resize();
	});

	$(window).on('scroll.cms', function(){
		cms_page_panel_button_show_scroll();
	});
	
	cms_page_panel_button_show_init();

	cms_page_panel_button_show_resize();
	
	cms_page_panel_button_show_scroll();
	
});
