( function( $ ) {
	
	// params.step = percentage to shrink at each try
    $.fn.cms_lightbox = function(params) {
    	
    	params = params || {};
    	
    	var images = [];
    	
    	$(this).each(function(){
    		
        	var $this = $(this);
        	
        	if ($this.hasClass('cms_lightbox_ok')){
        		return;
        	}
        	
        	$this.addClass('cms_lightbox_ok');
        	
        	var image = $this.data('cms_lightbox_image');
        	if (!image) {
        		
        		image = $this.css('background-image');
        		image = image.replace('url(', '').replace(')', '').replace(/"/g, '');

        	}
        	
        	if (!image || image == 'none'){
        		return;
        	}

        	if ($this.data('cms_lightbox_applied') != 1 && image != ''){

        		$this.data('cms_lightbox_applied', 1);

        		$this.css({'cursor':'pointer'});
        		
				images.push(image);
				$this.data('cms_lightbox_image', image);

				// create overlay
        		$this.on('click.cms', function(){
        			
        			cms_lock_scroll();
        			
        			var $this = $(this);
        			
        			$this.addClass('cms_lightbox_current');
        			
        			var background_colour = $this.data('cms_lightbox_background');
        			if (!background_colour){
        				background_colour = '#000000';
        			}
        			
        			// make it to rgb
        			$('body').append('<div id="_cms_lightbox_temp" style="background-color: ' + background_colour + '; "></div>');
        			background_colour = $('#_cms_lightbox_temp').css('background-color');
        			$('#_cms_lightbox_temp').remove();
        			background_colour = background_colour.replace(')', ',0.9)').replace('rgb(','rgba(');

    	        	$('body').append('<div class="cms_lightbox_overlay" style="height: 100vh; width: 100vw; position: fixed; top: 0px; left: 0px; padding: 20px; ' +
    	        			'background-color: ' + background_colour + '; z-index: 1000; cursor: pointer; opacity: 0; box-sizing: border-box; ">' +
    	        				'<div class="cms_lightbox_image" style="opacity: 0; background-repeat: no-repeat; background-position: center; ' +
    	        				'background-size: contain; width: 100%; height: 100%; ' +
    	        				'background-image: url(' + $this.data('cms_lightbox_image') + '); "></div>' +
    	        			'</div>');
    	        	
    	        	if ($this.data('cms_lightbox_close')){
    	        		$('.cms_lightbox_overlay').append('<div class="cms_lightbox_close" ' + 
    	        				'style="opacity: 0; background-image: url(' + $this.data('cms_lightbox_close') + '); "></div>');
    	        	}
    	        	
    	        	var $cms_lightbox_data = $('.cms_lightbox_data');
    	        	if ($cms_lightbox_data.length){
    	        		
    	        		var cms_lightbox_load = function($el){

    	        			$this = $el;
    	        			
    	        			$('.cms_lightbox_current').removeClass('cms_lightbox_current');
    	        			$this.addClass('cms_lightbox_current');
    	        			
    	        			
    	        			$('.cms_lightbox_image').animate({'opacity':'0'}, 300, function(){
    	        				$('.cms_lightbox_image').css({'background-image':'url(' + $this.data('cms_lightbox_image') + ')'});
    	        				$('.cms_lightbox_image').animate({'opacity':'1'}, 300);
    	        			});
    	        			
    	        			
    	        		}
    	        		
    	        		if ($cms_lightbox_data.data('arrow_left')){
    	        			
    	        			$('.cms_lightbox_overlay').append('<div class="cms_lightbox_arrow_left" style="background-image: url(' + $cms_lightbox_data.data('arrow_left') + '); "></div>');
    	        			
    	        			$('.cms_lightbox_arrow_left').on('click.joberryman', function(){
    	        				
    	        				var $prev = $();
    	        				var $next = $();
    	        				var found = false;
    	        				
    	        				var $cms_lightbox = $('.cms_lightbox');
    	        				$cms_lightbox.each(function(){
    	        					
    	        					if (!$(this).data('cms_lightbox_image') ){
    	        						return;
    	        					}  	
    	        					
    	        					if (found && $next.length == 0){
    	        						$next = $(this);
    	        					}
    	        					
    	        					if ($(this).hasClass('cms_lightbox_current')){
    	        						found = true;
    	        					}
    	        					
    	        					if (!found){
    	        						$prev = $(this);
    	        					}
    	        					
    	        				});
    	        				
    	        				if ($prev.length){
        	        				cms_lightbox_load($prev);
    	        				} else {
    	        					$('.cms_lightbox_image').click();
    	        				}
    	        				
    	        				
    	        				
    	        			});
    	        			    	        			
    	        		}
    	        		
    	        		if ($cms_lightbox_data.data('arrow_right')){
    	        			
    	        			$('.cms_lightbox_overlay').append('<div class="cms_lightbox_arrow_right" style="background-image: url(' + $cms_lightbox_data.data('arrow_right') + '); "></div>');
    	        			
    	        			$('.cms_lightbox_arrow_right').on('click.joberryman', function(){
    	        				
    	        				var $prev = $();
    	        				var $next = $();
    	        				var found = false;
    	        				
    	        				var $cms_lightbox = $('.cms_lightbox');
    	        				$cms_lightbox.each(function(){
    	        					
    	        					if (!$(this).data('cms_lightbox_image') ){
    	        						return;
    	        					}  	

    	        					if (found && $next.length == 0){
    	        						$next = $(this);
    	        					}
    	        					
    	        					if ($(this).hasClass('cms_lightbox_current')){
    	        						found = true;
    	        					}

    	        					if (!found){
    	        						$prev = $(this);
    	        					}
    	        					
    	        				});
    	        				
    	        				if ($next.length){
    	        					cms_lightbox_load($next);
    	        				} else {
    	        					$('.cms_lightbox_image').click();
    	        				}
    	        				
    	        				

    	        			});

    	        		}
    	        		
    	        		// register arrows
    	        		$(document).on('keydown.joberryman', function(e){
    	        			if (e.keyCode == 37){ // left
    	        				$('.cms_lightbox_arrow_left').click();
    	        			}
    	        			if (e.keyCode == 39){ // right
    	        				$('.cms_lightbox_arrow_right').click();
    	        			}
    	        			if (e.keyCode == 27){ // right
    	        				$('.cms_lightbox_image').click();
    	        			}
    	        		});
    	        		
    	        	}

    	        	$('.cms_lightbox_overlay').animate({'opacity':'1'}, 400);

    	        	$('.cms_lightbox_image').on('click.cms touchstart.cms', function(){
    	        		
    	        		$(document).off('keydown.joberryman');
    	        		
    	        		$('.cms_lightbox_close').animate({'opacity':'0'}, 300);
    	        		
    	        		$('.cms_lightbox_image').animate({'opacity':'0'}, 500, function(){
        	        		$('.cms_lightbox_overlay').animate({'opacity':'0'}, 300, function(){
            	        		$('.cms_lightbox_overlay').remove();
            	        		cms_unlock_scroll();
            	        		$('.cms_lightbox_current').removeClass('cms_lightbox_current');
            	        	});
        	        	});

    	        	});
    	        	setTimeout(function(){
    	        		$('.cms_lightbox_image,.cms_lightbox_close').animate({'opacity':'1'}, 400);
    	        	}, 600);
    	        	
    	    	});
        	
        	}

    	});
    	
    	setTimeout(function(){
    		
			var preloader = new preloader_class();
			preloader.preload({
				'images': images
			});
		
    	}, 3000);
    	
        return this;
 
    };
 
}( jQuery ));
