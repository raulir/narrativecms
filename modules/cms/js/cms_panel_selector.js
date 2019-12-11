
function cms_panel_selector_init(){
	
	cms_popup_run('panel_selector', function(){
		
		$('.cms_panel_selector_item').on('mouseenter.cms', function(){

			$('.cms_panel_selector_preview_item_' + $(this).data('hash')).addClass('cms_panel_selector_preview_item_active')
			
		})
		
		$('.cms_panel_selector_item').on('mouseleave.cms', function(){
			
			$('.cms_panel_selector_preview_item_active').removeClass('cms_panel_selector_preview_item_active')
			
		})
		
		$('.cms_panel_selector_item').on('click.cms', function(){
			
			$('.cms_panel_selector_select_disabled').removeClass('cms_panel_selector_select_disabled')

			$('.cms_panel_selector_item_selected').removeClass('cms_panel_selector_item_selected')
			$('.cms_panel_selector_preview_item_selected').removeClass('cms_panel_selector_preview_item_selected')

			$(this).addClass('cms_panel_selector_item_selected')
			$('.cms_panel_selector_preview_item_' + $(this).data('hash')).addClass('cms_panel_selector_preview_item_selected')
			
			$('.cms_panel_selector_shortcut_select').val('')
			
		})
		
		$('.cms_panel_selector_shortcut_select').on('change.cms', function(){
			
			if ($(this).val() == ''){
				
				if (!$('.cms_panel_selector_item_selected').length){
					$('.cms_panel_selector_select').addClass('cms_panel_selector_select_disabled')
				}
				
			} else {
				$('.cms_panel_selector_item_selected').removeClass('cms_panel_selector_item_selected')
				$('.cms_panel_selector_preview_item_selected').removeClass('cms_panel_selector_preview_item_selected')
				$('.cms_panel_selector_select').removeClass('cms_panel_selector_select_disabled')
			}
			
		})
		
		cms_panel_selector_init_filter(true)
		
		$('.cms_panel_selector_filter_select').on('change.cms', () => cms_panel_selector_init_filter(false))

	})
	
}

function cms_panel_selector_init_filter(show_all){
	
	var module = $('.cms_panel_selector_filter_select').val()

	if (show_all && $('.cms_panel_selector_item').length <= 10){
		module = ''
	}

	if (module == ''){
		
		$('.cms_panel_selector_item').removeClass('cms_panel_selector_item_hidden')

	} else {

		$('.cms_panel_selector_item').addClass('cms_panel_selector_item_hidden')
		$('.cms_panel_selector_item_' + module).removeClass('cms_panel_selector_item_hidden')
		
	}
	
}

function cms_panel_selector_resize(){

}

$(document).ready(function() {
		
	$(window).on('resize.cms', function(){
		cms_panel_selector_resize();
	});
	
	cms_panel_selector_init();
	
	cms_panel_selector_resize();

});
