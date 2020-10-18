
function cms_xy_picker_init(){
	
	cms_popup_run('xy_picker', function(){
	
		$('.cms_xy_picker_image_inner').each(function(){
		
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

				}, 100)
			
			}
			
			cms_xy_picker_pointer_update()

			$this.on('mousemove.cms', function(e){
				cms_xy_picker_cursor_update($this, e)
			})
			
			$this.on('click.cms', function(e){
				var offset = $this.offset()
				$('.cms_xy_picker_container').data('x', ((e.pageX - offset.left)/$this.width())*100)
				$('.cms_xy_picker_container').data('y', ((e.pageY - offset.top)/$this.height())*100)
				cms_xy_picker_cursor_update($this, e)
				cms_xy_picker_pointer_update()
			})

		})

	})
	
}

function cms_xy_picker_cursor_update($this, e){
	var offset = $this.offset()
	$('.cms_xy_picker_cursor_value')
		.html((((e.pageX - offset.left)/$this.width())*100).toFixed(2) + ' , ' + (((e.pageY - offset.top)/$this.height())*100).toFixed(2))
}

function cms_xy_picker_pointer_update(){
	$('.cms_xy_picker_current_value').html(( + $('.cms_xy_picker_container').data('x')).toFixed(2) + ' , ' + ( + $('.cms_xy_picker_container').data('y')).toFixed(2))
	$('.cms_xy_picker_pointer').css({'left':$('.cms_xy_picker_container').data('x') + '%', 'top':$('.cms_xy_picker_container').data('y') + '%', 'opacity':'1'})
}

function cms_xy_picker_resize(){

}

$(document).ready(function() {
		
	$(window).on('resize.cms', function(){
		cms_xy_picker_resize();
	});
	
	cms_xy_picker_init();
	
	cms_xy_picker_resize();

});
