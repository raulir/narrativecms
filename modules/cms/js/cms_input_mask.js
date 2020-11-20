function cms_input_mask_init(){
	
	$('.cms_input_mask_image_inner,.cms_input_mask_image_value').each(function(){
		
		var $this = $(this)
		
		if ($this.data('h')){
		
			var ratio = $this.data('w')/$this.data('h')
			var cratio = $this.parent().innerWidth()/$this.parent().innerHeight()
			if (ratio > cratio){
				$this.css({'width':$this.parent().innerWidth(), 'height': $this.parent().innerWidth()/ratio})
			} else {
				$this.css({'height':$this.parent().innerHeight(), 'width': $this.parent().innerHeight()*ratio})
			}

		}

	})
	
	$('.cms_input_mask_set_button').off('click.cms').on('click.cms', function(){
		$(this).closest('.cms_input_mask_container').addClass('cms_input_mask_active')
		cms_input_mask_picker($(this).closest('.cms_input_mask_container'))
	})
	
	$('.cms_input_mask_clear').off('click.cms').on('click.cms', function(){
		$('.cms_input_mask_value', $(this).closest('.cms_input_mask_container')).val('')
		$('.cms_input_mask_image_value', $(this).closest('.cms_input_mask_container')).remove()
	})
	
	$('.cms_input_mask_container').each(function(){
		cms_input_mask_display($(this))
	})

}

function cms_input_mask_picker($input){
	
	get_ajax_panel('cms/cms_mask_picker', {
		
		'image': $input.data('target_image'),
		'definition': $input.data('definition'),
		'value': $('.cms_input_mask_value', $input).val(),
		'name_hash': hex_md5($('.cms_page_panel_id') + ' ' + $input.data('name'))
	
	}, function(data){

		panels_display_popup(data.result.html, {
			'select': function(data){
			
				$('body').off('mousedown.mask')
				$('body').off('mouseup.mask')
			
				var value = {}
			
				value.width = $('.cms_mask_picker_container').data('width')
				value.height = $('.cms_mask_picker_container').data('height')
			
				value.values = []
				$('.cms_mask_picker_square').each(function(){
					
					if ($(this).hasClass('cms_mask_picker_square_active')){
						value.values.push(1)
					} else {
						value.values.push(0)
					}
					
				})
				
				$('.cms_input_mask_value', $('.cms_input_mask_active')).val(JSON.stringify(value))
				
				cms_input_mask_display($('.cms_input_mask_active'))

				$('.cms_input_mask_active').removeClass('cms_input_mask_active')
				$('.cms_popup_container').remove()
				return

			},
			'cancel': function(data){
				$('.cms_input_mask_active').removeClass('cms_input_mask_active')
				$('.cms_popup_container').remove()
			}
		}); 			
	});
	
}

function cms_input_mask_display($this){
	
	$('.cms_input_mask_mask').remove()
	
	if ($('.cms_input_mask_value', $this).val()){
		
		var value = JSON.parse($('.cms_input_mask_value', $this).val())

		$image = $('.cms_input_mask_image_inner', $this)
		
		var sx = $image.innerWidth()/value.width
		var sy = $image.innerHeight()/value.height
		
		for(var x = 0; x < value.width; x++){
			for(var y = 0; y < value.height; y++){
			
				if (value.values[x + (y * value.width)] == 1){
					$image.append('<div class="cms_input_mask_mask" style="left: ' + x*sx + 'px; top: ' + y*sy + 'px; width: ' + 
							sx + 'px; height: ' + sy + 'px; "></div>')
				}
				
			}
		}
		
	}
	
}

function cms_input_mask_resize(){
	
}

function cms_input_mask_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', cms_input_mask_resize)
	$(window).on('scroll.cms', cms_input_mask_scroll)
	
	cms_input_mask_init()
	cms_input_mask_resize()
	cms_input_mask_scroll()

})
