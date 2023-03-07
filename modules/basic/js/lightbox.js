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
		
		var $container = $('.basic_lightbox_container').first().detach()
		
		$('.basic_lightbox_container').remove()
		
		$container.appendTo('body')
	
	}, 3000);
	
	$('body').on('click.cms', '.basic_lightbox', function(){
    	
		var $this = $(this)
		
    	cms_scroll_lock()
    	
    	$this.addClass('basic_lightbox_current')

    	$('.basic_lightbox_container').css({'display':'block'}).addClass('basic_lightbox_active')
    	$('.basic_lightbox_content').css({'background-image': 'url(' + $this.data('basic_lightbox') + ')'})
    	
    	if($this.data('basic_lightbox_text')){
    		$('.basic_lightbox_text').css({'display':''}).html($this.data('basic_lightbox_text'))
    	} else {
    		$('.basic_lightbox_text').css({'display':'none'})
    	}
    	
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
    		if (e.keyCode == 39 || e.keyCode == 32){ // right + space
    			$('.basic_lightbox_next').click();
    		}
    		if (e.keyCode == 27){ // esc
    			$('.basic_lightbox_close').click();
    		}
    	});
    	
    	var $parent = $this.closest('.basic_lightbox_parent')
    	if (!$parent.length){
    		$parent = $this.parent()
    	}
    	
    	if ($('.basic_lightbox', $parent).length <= 1){
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
        		$('.basic_lightbox_container').css({'display':'none'}).removeClass('basic_lightbox_active');
        		cms_scroll_unlock();
        		$('.basic_lightbox_current').removeClass('basic_lightbox_current');
        	});
    	});

	});
	
	$('.basic_lightbox_previous').on('click.cms', function(){
		
		var $this = $(this)
	
		var $current = $('.basic_lightbox_current')
		$current.removeClass('basic_lightbox_current')

		if (!$current.length){
			return
		}
		
		var $parent = $current.closest('.basic_lightbox_parent')
    	if (!$parent.length){
    		$parent = $this.parent()
    	}
    	
    	var $items = $('.basic_lightbox', $parent)
    	var $new = false
    	var $temp = false
    	$items.each(function(){
    		if ($(this).is($current)){
    			$new = $temp
    			if ($new === false){
    				$new = $items.last()
    			}
    		}
    		$temp = $(this)
    	})
		
		if (!$new.length){
			return
		}
		
		$('.basic_lightbox_content').animate({'opacity':'0'}, 300, () => {
			$('.basic_lightbox_content').css({'background-image': 'url(' + $new.data('basic_lightbox') + ')'})
			$('.basic_lightbox_content').animate({'opacity':'1'}, 300, () => {
				$new.addClass('basic_lightbox_current')
			})
	    	if($new.data('basic_lightbox_text')){
	    		$('.basic_lightbox_text').css({'display':''}).html($new.data('basic_lightbox_text'))
    		} else {
    			$('.basic_lightbox_text').css({'display':'none'})
    		}
		})
		
	});

	$('.basic_lightbox_next').on('click.cms', function(){
	
		var $this = $(this)

		var $current = $('.basic_lightbox_current')
		$current.removeClass('basic_lightbox_current')

		if (!$current.length){
			return
		}
		
		var $parent = $current.closest('.basic_lightbox_parent')
    	if (!$parent.length){
    		$parent = $current.parent()
    	}
    	
    	var $items = $('.basic_lightbox', $parent)
    	var $new = false
    	var flag = false
    	$items.each(function(){
    		if (flag === true && $new === false){
    			$new = $(this)
    		}
    		if ($(this).is($current)){
    			flag = true
    		}
    	})
    	if ($new === false){
			$new = $items.first()
		}
		
		if (!$new.length){
			return
		}
		
		$('.basic_lightbox_content').animate({'opacity':'0'}, 300, () => {
			$('.basic_lightbox_content').css({'background-image': 'url(' + $new.data('basic_lightbox') + ')'})
			$('.basic_lightbox_content').animate({'opacity':'1'}, 300, () => {
				$new.addClass('basic_lightbox_current')
			})
	    	if($new.data('basic_lightbox_text')){
	    		$('.basic_lightbox_text').css({'display':''}).html($new.data('basic_lightbox_text'))
    		} else {
    			$('.basic_lightbox_text').css({'display':'none'})
    		}
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
