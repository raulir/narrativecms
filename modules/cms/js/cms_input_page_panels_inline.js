/*
 * Add child panel
 */
function cms_input_page_panels_inline_add(parent_id, input_name, allowed_panels){
	
	cms_input_page_panel_selector('panel', parent_id, input_name, allowed_panels, true)
		.then(data => {

			get_ajax_panel('cms/cms_page_panels_panel', {
				'cms_page_panel_id': 0,
				'panel_name': data.panel_name,
				'parent_field_name': input_name
			}, function(new_panel_data){

				if ($('.cms_input_page_panels_inline_message').length){
					$('.cms_input_page_panels_inline_message').closest('.cms_input_page_panels_inline_container').data('cms_input_height', 3)
					$('.cms_input_page_panels_inline_message').remove()
				}
				
				$('.cms_input_page_panels_inline_panels').append(new_panel_data.result._html)
				
				cms_page_panel_fields_init()
				
			})

		})

}

function cms_input_page_panels_inline_init(){

	$('.cms_input_page_panels_inline_add').on('click.cms', function(){
		
		var $this = $(this);

		var cms_page_id = $('.cms_page_id').val();
		var parent_id = $('.cms_page_panel_id').val();
		var input_name = $this.data('name')
		var allowed_panels = $this.data('panels')

		if (parent_id == 0){
			
			// ask are you sure
			get_ajax_panel('cms/cms_popup_yes_no', {
				'text': 'Page panel is not saved. Save the panel?'
			}, function(data){
				panels_display_popup(data.result._html, {
					'yes': function(){
						
						cms_page_panel_save({
							'success':function(data){
								
								cms_input_page_panels_inline_add(parent_id, input_name, allowed_panels)
							
							}
						})

					}
				})
			})

		} else {
			
			cms_input_page_panels_inline_add(parent_id, input_name, allowed_panels)
			
		}
		
	})
	
}

function cms_input_page_panels_inline_resize(){
	
}

function cms_input_page_panels_inline_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', cms_input_page_panels_inline_resize)
	$(window).on('scroll.cms', cms_input_page_panels_inline_scroll)
	
	cms_input_page_panels_inline_init()
	cms_input_page_panels_inline_resize()
	cms_input_page_panels_inline_scroll()

})
