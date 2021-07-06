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

}

function cms_page_panels_panel_init(){

	$('.cms_input_page_panels_inline_panels').sortable({
		'start': function(){
			tinyMCE.triggerSave()
		},
		'stop': function(){
			$(this).find('.cms_tinymce').each(function(){
   				tinyMCE.execCommand( 'mceRemoveEditor', false, $(this).attr('id') )
	            tinyMCE.execCommand( 'mceAddEditor', false, $(this).attr('id') )
	    	})
    	}
	}).disableSelection();
	
	$('.cms_page_panels_panel_hide').each(function(){
		
		var $this = $(this)
		
		if ($this.hasClass('cms_page_panels_panel_hide_ok')) return
		
		$this.addClass('cms_page_panels_panel_hide_ok')
		
		$this.on('click.cms', function(){
			
			var $container = $this.closest('.cms_page_panels_panel_container')
			
			if($container.data('cms_page_panel_id')){
			
				cms_page_panels_panel_hide($container.data('cms_page_panel_id'), !$container.hasClass('cms_page_panels_panel_hidden'), function(result){
					
					if (result){
						$container.removeClass('cms_page_panels_panel_hidden')
						$this.html('Hide')
					} else {
						$container.addClass('cms_page_panels_panel_hidden')
						$this.html('Show')
					}
					
				})
			
			} else {
			
				if($container.hasClass('cms_page_panels_panel_hidden')){
					$container.removeClass('cms_page_panels_panel_hidden')
					$this.html('Hide')
				} else {
					$container.addClass('cms_page_panels_panel_hidden')
					$this.html('Show')
				}
			
			}

		})
	
	})

	$('.cms_page_panels_panel_delete').each(function(){
		
		var $this = $(this)
		
		if ($this.hasClass('cms_page_panels_panel_delete_ok')) return
		
		$this.addClass('cms_page_panels_panel_delete_ok')
		
		$this.on('click.cms', function(){
		
			$this.closest('.cms_page_panels_panel_container').remove()
			
			cms_page_panel_fields_init()
		
		})

	})

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




