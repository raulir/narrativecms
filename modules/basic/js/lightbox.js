function basic_lightbox_init(){
	
	var images = []
	
	$('.basic_lightbox').each(function(){
		
		var $this = $(this)
		var image = $this.data('basic_lightbox')
		
		images.push(image)

	})
	
	setTimeout(function(){
		
		cms_preloader.preload({
			'images': images
		});
	
	}, 3000);
	
	$('.userchat_messages').on('click.cms', '.basic_lightbox', function(){
    	
		var $this = $(this)
		
    	cms_lock_scroll()
    	
    	$this.addClass('basic_lightbox_current')

    	$('.basic_lightbox_container').css({'display':'block'})
    	$('.basic_lightbox_content').css({'background-image': 'url(' + $this.data('basic_lightbox') + ')'})
    	
    	setTimeout(() => {
    	
        	$('.basic_lightbox_container').animate({'opacity':'1'}, 400);

        	setTimeout(function(){
        		$('.basic_lightbox_content').animate({'opacity':'1'}, 400);
        	}, 100);
    		
    	}, 100)

    	// register arrows
    	$(document).on('keydown.lightbox', function(e){
    		if (e.keyCode == 37){ // left
    			$('.basic_lightbox_previous').click();
    		}
    		if (e.keyCode == 39){ // right
    			$('.basic_lightbox_next').click();
    		}
    		if (e.keyCode == 27){ // esc
    			$('.basic_lightbox_close').click();
    		}
    	});
    	
    	if (!$this.siblings('.basic_lightbox').length){
    		$('.basic_lightbox_previous,.basic_lightbox_next').css({'display':'none'})
    	} else {
    		$('.basic_lightbox_previous,.basic_lightbox_next').css({'display':''})
    	}
    	
	})

	$('.basic_lightbox_content,.basic_lightbox_close').on('click.cms touchstart.cms', function(){
		
		$(document).off('keydown.lightbox');
		
		$('.basic_lightbox_close').animate({'opacity':'0'}, 300);
		setTimeout(() => {
			$('.basic_lightbox_close').css({'opacity':''});
		}, 800)
		
		$('.basic_lightbox_content').animate({'opacity':'0'}, 500, function(){
    		$('.basic_lightbox_container').animate({'opacity':'0'}, 300, function(){
        		$('.basic_lightbox_container').css({'display':'none'});
        		cms_unlock_scroll();
        		$('.basic_lightbox_current').removeClass('basic_lightbox_current');
        	});
    	});

	});
	
	$('.basic_lightbox_previous').on('click.cms', function(){
	
		var $current = $('.basic_lightbox_current')
		$current.removeClass('basic_lightbox_current')

		if (!$current.length){
			return
		}
		
		var $new = $current.prevAll('.basic_lightbox').first()
		if (!$new.length){
			$new = $current.nextAll('.basic_lightbox').last()
		}
		
		if (!$new.length){
			return
		}
		
		$('.basic_lightbox_content').animate({'opacity':'0'}, 300, () => {
			$('.basic_lightbox_content').css({'background-image': 'url(' + $new.data('basic_lightbox') + ')'})
			$('.basic_lightbox_content').animate({'opacity':'1'}, 300, () => {
				$new.addClass('basic_lightbox_current')
			})
		})
		
	});

	$('.basic_lightbox_next').on('click.cms', function(){
	
		var $current = $('.basic_lightbox_current')
		$current.removeClass('basic_lightbox_current')

		if (!$current.length){
			return
		}
		
		var $new = $current.nextAll('.basic_lightbox').first()
		if (!$new.length){
			$new = $current.prevAll('.basic_lightbox').last()
		}
		
		if (!$new.length){
			return
		}
		
		$('.basic_lightbox_content').animate({'opacity':'0'}, 300, () => {
			$('.basic_lightbox_content').css({'background-image': 'url(' + $new.data('basic_lightbox') + ')'})
			$('.basic_lightbox_content').animate({'opacity':'1'}, 300, () => {
				$new.addClass('basic_lightbox_current')
			})
		})
		
	});
	
}

function basic_lightbox_resize(){

}

function basic_lightbox_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		basic_lightbox_resize();
	});
	
	$(window).on('scroll.cms', function(){
		basic_lightbox_scroll();
	});
	
	basic_lightbox_init();

	basic_lightbox_resize();
	
	basic_lightbox_scroll();

});
