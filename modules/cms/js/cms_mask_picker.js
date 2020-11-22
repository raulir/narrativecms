var cms_mask_mousedown = 0
var cms_mask_mode = 0

function cms_mask_picker_init(){
	
	cms_popup_run('mask_picker', function(){
	
		$('.cms_mask_picker_image_inner').each(function(){
		
			var $this = $(this)

			if ($this.data('h')){
			
				setTimeout(() => {
					
					var ratio = $this.data('w')/$this.data('h')
					var cratio = $this.parent().innerWidth()/$this.parent().innerHeight()
	
					if (ratio > cratio){
						$this.css({'width':$this.parent().innerWidth(), 'height': $this.parent().innerWidth()/ratio})
					} else {
						$this.css({'height':$this.parent().innerHeight(), 'width': $this.parent().innerHeight()*ratio})
					}

				}, 30)
				
				setTimeout(() => {
					
					// create grid
					var pic_x = $this.innerWidth()
					var pic_y = $this.innerHeight()
				
					var unit_px = (pic_x + pic_y) / (2 * $this.closest('.cms_mask_picker_container').data('definition'))
					
					var units_x = Math.round(pic_x/unit_px)
					var units_y = (2 * $this.closest('.cms_mask_picker_container').data('definition')) - units_x
					
					$('.cms_mask_picker_container').data({'width': units_x, 'height': units_y})
					
					var px_x = pic_x/units_x
					var px_y = pic_y/units_y
					
					// calculate translated scales
					var value_json = $('.cms_input_mask_value', $('.cms_input_mask_active')).val()
					
					if (!value_json){
						var value = {
							'values': ['0'],
							'width': 1,
							'height': 1
						}
					} else {
						var value = JSON.parse(value_json)
					}
					
					var kx = units_x/value.width

					var tx = {}
					for (var i = 0; i < units_x; i++){
						tx[i] = Math.floor(i / kx)
					}
					
					var ky = units_y/value.height
					var ty = {}
					for (var i = 0; i < units_y; i++){
						ty[i] = Math.floor(i / ky)
					}

					for(var iy = 0; iy < units_y; iy++){
						for(var ix = 0; ix < units_x; ix++){
						
							// find the closest value
							var val = value.values[tx[ix] + (ty[iy] * value.width)] // value.values[x + (y * value.width)]
						
							$this.append('<div class="cms_mask_picker_square ' + (val == 1 ? ' cms_mask_picker_square_active ' : '') + '" style="left: ' 
									+ (ix * px_x) + 'px; top: ' + (iy * px_y) + 'px; width: ' + px_x + 'px; height: ' + px_y + 'px; "></div>')
						
						}
					}

					$('body').off('mousedown.mask').on('mousedown.mask', function(e){
						cms_mask_mousedown = 1
					})
					$('body').off('mouseup.mask').on('mouseup.mask', function(e){
						cms_mask_mousedown = 0
					})
					
					$('.cms_mask_picker_mark').on('click.cms', function(){
						cms_mask_mode = 0
						$('.cms_mask_picker_button_active').removeClass('cms_mask_picker_button_active')
						$(this).addClass('cms_mask_picker_button_active')
					}).click()
					
					$('.cms_mask_picker_erase').on('click.cms', function(){
						cms_mask_mode = 1
						$('.cms_mask_picker_button_active').removeClass('cms_mask_picker_button_active')
						$(this).addClass('cms_mask_picker_button_active')
					})
					
					$('.cms_mask_picker_clear').on('click.cms', function(){
						$('.cms_mask_picker_square_active').removeClass('cms_mask_picker_square_active')
					})
					
					$('.cms_mask_picker_square').on('mouseenter.cms', function(){
						if (cms_mask_mousedown){
							if (cms_mask_mode){
								$(this).removeClass('cms_mask_picker_square_active')
							} else {
								$(this).addClass('cms_mask_picker_square_active')
							}
						}
					})
					
					$('.cms_mask_picker_square').on('contextmenu.cms', () => {return false}).on('click.cms', function(e){
						if (cms_mask_mode){
							$(this).removeClass('cms_mask_picker_square_active')
						} else {
							$(this).addClass('cms_mask_picker_square_active')
						}
					})
					
				}, 60)
			
			}

		})

	})
	
}

function cms_mask_picker_cursor_update($this, e){
	var offset = $this.offset()
	$('.cms_mask_picker_cursor_value')
		.html((((e.pageX - offset.left)/$this.width())*100).toFixed(2) + ' , ' + (((e.pageY - offset.top)/$this.height())*100).toFixed(2))
}

function cms_mask_picker_pointer_update(){
	$('.cms_mask_picker_current_value').html(( + $('.cms_mask_picker_container').data('x')).toFixed(2) + ' , ' + ( + $('.cms_mask_picker_container').data('y')).toFixed(2))
	$('.cms_mask_picker_pointer').css({'left':$('.cms_mask_picker_container').data('x') + '%', 'top':$('.cms_mask_picker_container').data('y') + '%', 'opacity':'1'})
}

function cms_mask_picker_resize(){

}

$(document).ready(function() {
		
	$(window).on('resize.cms', function(){
		cms_mask_picker_resize();
	});
	
	cms_mask_picker_init();
	
	cms_mask_picker_resize();

});
