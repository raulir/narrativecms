function cms_input_xy_init(){
	
	$('.cms_input_xy_image_inner').each(function(){
		
		var $this = $(this)
		var $container = $this.closest('.cms_input_xy_container')
		
		if ($this.data('h')){
		
			var ratio = $this.data('w')/$this.data('h')
			var cratio = $this.parent().innerWidth()/$this.parent().innerHeight()
			if (ratio > cratio){
				$this.css({'width':$this.parent().innerWidth(), 'height': $this.parent().innerWidth()/ratio})
			} else {
				$this.css({'height':$this.parent().innerHeight(), 'width': $this.parent().innerHeight()*ratio})
			}
			
			$('.cms_input_xy_pointer', $container).css({'left':$('.cms_input_xy_x', $container).val() + '%', 'top':$('.cms_input_xy_y', $container).val() + '%'})
		
		}

	})
	
	$('.cms_input_xy_set_button').off('click.cms').on('click.cms', function(){
		$(this).closest('.cms_input_xy_container').addClass('cms_input_xy_active')
		cms_input_xy_picker($(this).closest('.cms_input_xy_container'))
	})
	
	$('.cms_input_xy_clear').off('click.cms').on('click.cms', function(){
		$('.cms_input_xy_x', $(this).closest('.cms_input_xy_container')).val('')
		$('.cms_input_xy_y', $(this).closest('.cms_input_xy_container')).val('')
		$('.cms_input_xy_pointer', $(this).closest('.cms_input_xy_container')).css({'opacity':''})
	})
	
	$('.cms_input_xy_x').off('change.cms keyup.cms').on('change.cms keyup.cms', function(){
		cms_input_xy_init()
	})

}

function cms_input_xy_picker($input){
	
	get_ajax_panel('cms/cms_xy_picker', {
		
		'image': $input.data('target_image'),
		'x': $('.cms_input_xy_x', $input).val(),
		'y': $('.cms_input_xy_y', $input).val()
	
	}, function(data){

		panels_display_popup(data.result._html, {
			'select': function(data){
			
				$('.cms_input_xy_x', $('.cms_input_xy_active')).val($('.cms_xy_picker_container').data('x').toFixed(2))
				$('.cms_input_xy_y', $('.cms_input_xy_active')).val($('.cms_xy_picker_container').data('y').toFixed(2))
				$('.cms_input_xy_pointer', $('.cms_input_xy_active'))
					.css({'left':$('.cms_input_xy_x', $('.cms_input_xy_active')).val() + '%', 'top':$('.cms_input_xy_y', $('.cms_input_xy_active')).val() + '%'})
				
				$('.cms_input_xy_active').removeClass('cms_input_xy_active')
				$('.cms_popup_container').remove()
				return

			},
			'cancel': function(data){
				$('.cms_input_xy_active').removeClass('cms_input_xy_active')
				$('.cms_popup_container').remove()
			}
		}); 			
	});
	
}

function cms_input_xy_resize(){
	
}

function cms_input_xy_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', cms_input_xy_resize)
	$(window).on('scroll.cms', cms_input_xy_scroll)
	
	cms_input_xy_init()
	cms_input_xy_resize()
	cms_input_xy_scroll()

})
