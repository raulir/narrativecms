var lightbox_current = 0
var $lightbox_images = $()

function lightbox_init(){
			
	setTimeout(function(){
		
		// images to preload
		var preload = []
		$('.lightbox_image').each(function(){
			var $this = $(this)
			var image = $this.data('lightbox_image')
			preload.push(image)
		})
		cms_preloader.preload({
			'images': preload
		})
		
		// lightbox html
		if ($('.lightbox_container').length == 0){
			get_ajax_panel('lightbox/lightbox').then((data) => {
				$(document.body).append(data.result._html)
			})
		}

	}, 1000)
	
	$('body').on('click.cms', '.lightbox_button', function(){

		var $this = $(this)
		
		if ($this.data('lightbox_images')){
			$lightbox_images = $this.data('lightbox_images')
		} else {
			$lightbox_images = $('.lightbox_image')
		}

		// lightbox current image index
		if ($this.data('lightbox_current') !== 'undefined'){
			lightbox_current = $this.data('lightbox_current')
		}
		
		var $lightbox_current = $($lightbox_images.get(lightbox_current))
		$lightbox_current.addClass('lightbox_current')

    	cms_scroll_lock()

    	$('.lightbox_container').css({'display':'block'}).addClass('lightbox_active')
    	$('.lightbox_content').css({'background-image': 'url(' + $lightbox_current.data('lightbox_image') + ')'})
    	
    	if($lightbox_current.data('lightbox_text')){
    		$('.lightbox_text').css({'display':''}).html($lightbox_current.data('lightbox_text'))
    	} else {
    		$('.lightbox_text').css({'display':'none'})
    	}
//    	
    	setTimeout(() => {
    	
        	$('.lightbox_container').animate({'opacity':'1'}, 400);

        	setTimeout(function(){
        		$('.lightbox_content').animate({'opacity':'1'}, 400);
        	}, 100);
    		
    	}, 100)

    	// register arrows
    	$(document).on('keydown.lightbox', function(e){
    		if (e.keyCode == 37){ // left
    			$('.lightbox_previous').click();
    		}
    		if (e.keyCode == 39 || e.keyCode == 32){ // right + space
    			$('.lightbox_next').click();
    		}
    		if (e.keyCode == 27){ // esc
    			$('.lightbox_close').click();
    		}
    	});
    	
    	if ($lightbox_images.length <= 1){
    		$('.lightbox_previous,.lightbox_next').css({'display':'none'})
    	} else {
    		$('.lightbox_previous,.lightbox_next').css({'display':''})
    	}
    	
	})

	$('.lightbox_content,.lightbox_close').on('click.cms touchstart.cms', function(){
		
		$(document).off('keydown.lightbox');
		
		$('.lightbox_close').animate({'opacity':'0'}, 300);
		setTimeout(() => {
			$('.lightbox_close').css({'opacity':''});
		}, 800)
		
		$('.lightbox_content').animate({'opacity':'0'}, 500, function(){
    		$('.lightbox_container').animate({'opacity':'0'}, 300, function(){
        		$('.lightbox_container').css({'display':'none'}).removeClass('lightbox_active');
        		cms_scroll_unlock();
         	});
    	});

	});

	$('.lightbox_previous').on('click.cms', function(){
	
		var $lightbox_current = $($lightbox_images.get(lightbox_current))

		$lightbox_current.removeClass('lightbox_current')

		if (!$lightbox_current.length){
			return
		}
		
		var $temp = $lightbox_images.last()
		var $new = $()
		
     	$lightbox_images.each(function(){
    		if ($(this).is($lightbox_current)){
    			$new = $temp
    		}
	   		$temp = $(this)
    	})

		if (!$new.length){
			return
		}
		
		lightbox_current = $lightbox_images.index($new)
		
		$('.lightbox_content').animate({'opacity':'0'}, 300, () => {
			$('.lightbox_content').css({'background-image': 'url(' + $new.data('lightbox_image') + ')'})
			$('.lightbox_content').animate({'opacity':'1'}, 300, () => {
				$new.addClass('lightbox_current')
			})
	    	if($new.data('lightbox_text')){
	    		$('.lightbox_text').css({'display':''}).html($new.data('lightbox_text'))
    		} else {
    			$('.lightbox_text').css({'display':'none'})
    		}
		})
		
	});

	$('.lightbox_next').on('click.cms', function(){
	
		var $lightbox_current = $($lightbox_images.get(lightbox_current))

		$lightbox_current.removeClass('lightbox_current')

		if (!$lightbox_current.length){
			return
		}
		
		var $temp = $lightbox_images.first()
		var $new = $()
		
		for (var i = $lightbox_images.length - 1; i >= 0; i--) {
    		var $_this = $lightbox_images.eq(i)
    		if ($_this.is($lightbox_current)){
    			$new = $temp
    		}
	   		$temp = $_this
    	}

		if (!$new.length){
			return
		}
		
		lightbox_current = $lightbox_images.index($new)

		$('.lightbox_content').animate({'opacity':'0'}, 300, () => {
			$('.lightbox_content').css({'background-image': 'url(' + $new.data('lightbox_image') + ')'})
			$('.lightbox_content').animate({'opacity':'1'}, 300, () => {
				$new.addClass('lightbox_current')
			})
	    	if($new.data('lightbox_text')){
	    		$('.lightbox_text').css({'display':''}).html($new.data('lightbox_text'))
    		} else {
    			$('.lightbox_text').css({'display':'none'})
    		}
		})
		
	});
	
}

function lightbox_resize(){

}

function lightbox_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		lightbox_resize();
	});
	
	$(window).on('scroll.cms', function(){
		lightbox_scroll();
	});
	
	lightbox_init();

	lightbox_resize();
	
	lightbox_scroll();

});
