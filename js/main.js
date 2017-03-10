/* framework related javascript */

// if no console - IE <= 9
if(!window.console) {
	window.console = {};
	window.console.log = function(str) {};
	window.console.dir = function(str) {};
}

Object.keys = Object.keys || function(o) { 
    var keysArray = []; 
    for(var name in o) { 
        if (o.hasOwnProperty(name)) 
          keysArray.push(name); 
    } 
    return keysArray; 
}

if (typeof String.prototype.endsWith !== 'function') {
    String.prototype.endsWith = function(suffix) {
        return this.indexOf(suffix, this.length - suffix.length) !== -1;
    };
}

function stackTrace() {
    var err = new Error();
    return err.stack;
}

function lock_scroll(){
	cms_lock_scroll();
}

function cms_lock_scroll(){
	
    $html = $('html'); 
    $body = $('body'); 
    var initWidth = $body.outerWidth();
    var initHeight = $body.outerHeight();

    var scrollPosition = [
        self.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft,
        self.pageYOffset || document.documentElement.scrollTop  || document.body.scrollTop
    ];
    $html.data('scroll_position', scrollPosition);
    
    var previous_overflow_x = $html.css('overflow-x');
    var previous_overflow_y = $html.css('overflow-y');
    
    if (previous_overflow_x != 'hidden'){
    	$html.data('previous_overflow_x', previous_overflow_x);
    }
    if (previous_overflow_y != 'hidden'){
    	$html.data('previous_overflow_y', previous_overflow_y);
    }

    $html.css('overflow', 'hidden');
    window.scrollTo(scrollPosition[0], scrollPosition[1]);   

    var marginR = $body.outerWidth()-initWidth;
    var marginB = $body.outerHeight()-initHeight; 
    $body.css({'margin-right': marginR,'margin-bottom': marginB});

}

function unlock_scroll(){
	cms_unlock_scroll();
}

function cms_unlock_scroll(){

	$html = $('html');
    $body = $('body');

    var previous_overflow_x = $html.data('previous_overflow_x');
    var previous_overflow_y = $html.data('previous_overflow_y');
    
    if (previous_overflow_x && previous_overflow_x != 'hidden'){
    	$html.css('overflow-x', previous_overflow_x);
    } else {
    	$html.css('overflow-x', '');
    }

    if (previous_overflow_y && previous_overflow_y != 'hidden'){
    	$html.css('overflow-y', previous_overflow_y);
    } else {
    	$html.css('overflow-y', '');
    }

    var scrollPosition = $html.data('scroll_position');
    window.scrollTo(scrollPosition[0], scrollPosition[1]);    

    $body.css({'margin-right': '', 'margin-bottom': ''});
    
}

function preg_quote( str ) {
    return (str+'').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, "\\$1");
}

function change_url(new_url){
	if (history && history.pushState) {

		if ( !window.location.href.endsWith(new_url) || new_url == '/'){
			history.pushState({}, '', new_url);
			cms_last_url = window.location.href;
		}

	}
}

function cookie_create(name, value, days) {
    var expires;

    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toGMTString();
    } else {
        expires = "";
    }
    document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + expires + "; path=/";
}

function cookie_read(name) {
    var nameEQ = encodeURIComponent(name) + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
    }
    return null;
}

function cookie_erase(name) {
    createCookie(name, "", -1);
}

function cms_hover_init(){

	// hover button changes
	$('.cms_hover_button').each(function(){
		if (!$(this).data('normal_image')){
			$(this).data('normal_image', $(this).css('background-image'));
		}
	});
	$('.cms_hover_button').on('mouseenter.sc', function(){
		var $this = $(this);
		if (!$this.hasClass('cms_hover_disabled')){
			$this.addClass($this.data('hover_class'));
			setTimeout(function(){
				$this.css({'background-image':$this.data('hover_image')});
			}, 150);
		};
	});
	$('.cms_hover_button').on('mouseleave.sc', function(){
		var $this = $(this);
		if (!$this.hasClass('cms_hover_disabled')){
			$this.removeClass($this.data('hover_class'));
			setTimeout(function(){
				$this.css({'background-image':$this.data('normal_image')});
			}, 150);
		}
	});
}

var get_ios_windowheight = function() {
    var zoomLevel = document.documentElement.clientWidth / window.innerWidth;
    return window.innerHeight * zoomLevel;
};

( function( $ ) {
	 
    $.fn.equalize_height = function() {
    	
    	var $set = this;
 
    	$set.css({'height':'', 'min-height':''});

    	for (var i = 0; i <= 9; i++) {

    		$set.each(function(){

    			var $this = $(this);
    			var max_height = 0;
    			var top = $this.offset().top;

    			$set.each(function(){
    				
    				var $that = $(this);
    				if ($that.offset().top == top && $that.height() > max_height){
    					max_height = $that.height();
    				}
    				
    			});
    			
    			if (max_height > 0 && !$this.data('max_height_set')){
    				$this.css({'min-height': Math.ceil(max_height) + 'px'}).data('max_height_set', true);
    			}
    	
    		});
    	
    	}
    	
    	$set.data('max_height_set', false);
 
        return $set;
 
    };
 
}( jQuery ));

( function( $ ) {
	
	// params.step = percentage to shrink at each try
    $.fn.equalize_content = function(params) {
    	
    	params = params || {};
    	
    	var try_narrower = function($item, iteration){
    		
    		var check_result = function(){
    			
    			if ($item.innerHeight() > old_h || iteration > 10){
    				
    				// bad
    				if (iteration <= 10){
    					$item.innerWidth(old_w);
    				}
    				
    				$item.css({'max-height':''}).css({'color':''});
    				
    				todo = todo - 1;
    				if (todo == 0){
    					$item.data('equalize_content', '');
    					params.after();
    				}
 
    			} else {
    			
    				try_narrower($item, iteration + 1);
    			
    			}

    		}
    		
    		if (!iteration){
    			iteration = 0;
    		}
    		
    		var old_w = $item.innerWidth();
    		var old_h = $item.innerHeight();
    		
    		$item.innerWidth(old_w * (1 - params.step/100));
    		
    		// if (typeof window.requestAnimationFrame === 'function'){
    		// 	requestAnimationFrame(check_result);
    		// } else {
    			setTimeout(check_result, 0);
    		// }
    		
    	}
    	
    	if (!params.step) params.step = 10;
    	if (!params.after) params.after = function(){};
    	
    	if (this.length == 0) {
    		params.after();
    		return this;
    	}
    	
    	var window_width = $(window).width();

    	var todo = this.length;
    	
    	this.each(function(){
    		
        	var $container = $(this);
        	
        	// only if page width or parent width has changed
        	var parent_width = $container.parent().width();
        	if ($container.data('window_width') != window_width || $container.data('parent_width') != parent_width){
        		
        		$container.data('window_width', window_width);
        		$container.data('parent_width', parent_width);
        		
        		if ($container.data('equalize_content') !== '1'){
                	
    	        	$container.css({'width':'','max-height':'none','color':'transparent'}).data('equalize_content', '1');
    	        	
    	        	if ($container.innerHeight() > 0){
    	        		setTimeout(function(){
    	            		try_narrower($container);
    	        		}, 30);
    	        	}
            	
            	}

        	}
        	
    	});

        return this;
 
    };
 
}( jQuery ));

( function( $ ) {
	
	// params.step = percentage to shrink at each try
    $.fn.cms_lightbox = function(params) {
    	
    	params = params || {};
    	
    	var images = [];
    	
    	$(this).each(function(){
    		
        	var $this = $(this);
        	
        	var image = $this.data('cms_lightbox_image');
        	if (!image) {
        		
        		image = $this.css('background-image');
        		image = image.replace('url(', '').replace(')', '').replace(/"/g, '');

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
    	        	
    	        	$('.cms_lightbox_overlay').animate({'opacity':'1'}, 400).on('click.cms touchstart.cms', function(){
    	        		
    	        		$('.cms_lightbox_close').animate({'opacity':'0'}, 300);
    	        		
    	        		$('.cms_lightbox_image').animate({'opacity':'0'}, 500, function(){
        	        		$('.cms_lightbox_overlay').animate({'opacity':'0'}, 300, function(){
            	        		$('.cms_lightbox_overlay').remove();
            	        		cms_unlock_scroll();
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

( function( $ ) {
	
    $.fn.cms_scroll_top = function(params) {
    	
    	params = params || {};
    	
    	$(this).each(function(){
    		
    		var $this = $(this);
    		
    		if ($this.data('cms_scroll_top') != '1'){

    			$(this).on('click.cms', function(){
	    			$('html, body').animate({ 
	    				scrollTop: 0
	    			}, 800);
	    		});
	    		
    			$this.data('cms_scroll_top', '1');

    		}

    	});
    	
    };
    
}( jQuery ));

( function( $ ) {
	
    $.fn.cms_scroll_down = function(params) {
    	
    	params = params || {};
    	
    	$(this).each(function(){
    		
    		var $this = $(this);
    		
    		if (!$this.data('cms_scroll_down_init')){

    			$this.on('click.cms', function(){
    				
    				var $this = $(this);
    				
    				// filter out not visible cms_headers
    				var $body = $('body');
    				var $header = $('.cms_header').filter(function(){
    					
    					var $parent = $(this);
    					
    					if ($parent.is($this.closest('.cms_container'))){
    						return false;
    					}

    					while ($parent.get(0) != $body.get(0)){
    						
    						if ($parent.css('display') == 'none'){
    							return false;
    						} else {
    							$parent = $parent.parent();
    						}
    						
    					}
    					
    					return true;
    					
    				});
    				
    				var position = $(this).closest('.cms_container').offset().top + $(this).closest('.cms_container').outerHeight();
    				
    				// test it 4 times
    				
    				var header_height = 0;
    				var i = 4;
    				
    				var interval_f = function(){
    					
    					if (i == 0){
    						clearInterval(interval);
    					}
        				
    					if ($header.length){
        					header_height = $header.height();
        				}
        				
        				$('html, body').stop().animate({
        					scrollTop: position - header_height
        				}, i*200);
        				
        				i = i - 1;
        				
    				}
    				
        			interval_f();
        			
    				var interval = setInterval(interval_f, 200);
    				
    			});
    			
    			$this.data('cms_scroll_down_init', true);

    		}

    	});

    	return this;
    	
    };
    
}( jQuery ));

// detect if touch
function is_touch_event(e){
	if (e.type == 'touchstart' || e.type == 'touchmove' || e.type == 'touchend'){
		return true;
	} else {
		return false;
	}
}

/* 
 * params after(), $this
 * 
 */
function cms_video_resize(params){
	
	var $this = params.$this;
	
	$this.css({
		'width': '',
		'height': ''
	});
	
	var video_width = $this.width();
	var video_height = $this.height();
	
	if ($this[0].readyState !== 4 || video_width == 0 || video_height == 0){
		
		setTimeout(function(){
			cms_video_resize(params);
		}, 800);
		
		return;
	
	}

	var $parent = $this.parent();
		
	var parent_width = $parent.width();
	var parent_height = $parent.height();
	
	var video_ratio = video_width/video_height;
	var parent_ratio = parent_width/parent_height;
	
	if (video_ratio > parent_ratio){
		video_height = parent_height;
		video_width = video_height * video_ratio;
	} else {
		video_width = parent_width;
		video_height = video_width / video_ratio;
	}

// console.log('new width: ' + video_width + ' new height: ' + video_height);
	
	$this.css({
		'width': (video_width/parent_width)*100 + '%',
		'height': (video_height/parent_height)*100 + '%',
		'top': - (video_height - parent_height)/(2*parent_height)*100 + '%',
		'left': - (video_width - parent_width)/(2*parent_width)*100 + '%'
	});
	
//	$this.data('state', 'pause');
	
	params.after();

}

( function( $ ) {
	
    $.fn.cms_restart_gif = function(params) {
    	
    	params = params || {};
    	
    	var $set = $(this);
		
		if (typeof cms_restart_gif_images == 'undefined'){
			cms_restart_gif_images = {};
		}

		$set.each(function(){

		    var element = $(this).get(0);
		    // code part from: http://stackoverflow.com/a/14013171/1520422
		    var style = element.currentStyle || window.getComputedStyle(element, false);
		    var bgImg = style.backgroundImage.slice(4, -1).replace(/"/g, '');
		    var helper = cms_restart_gif_images[bgImg]; // we cache our image instances
		    if (!helper) {
		      helper = $('<img>')
		        .attr('src', bgImg)
		        .css({
		          position: 'absolute',
		          left: '-5000px'
		        }) // make it invisible, but still force the browser to render / load it
		        .appendTo('body')[0];
		      cms_restart_gif_images[bgImg] = helper;
		      setTimeout(function() {
		        helper.src = bgImg;
		      }, 10);
		      // the first call does not seem to work immediately (like the rest, when called later)
		      // i tried different delays: 0 & 1 don't work. With 10 or 100 it was ok.
		      // But maybe it depends on the image download time.
		    } else {
		      // code part from: http://stackoverflow.com/a/21012986/1520422
		      helper.src = bgImg;
		    }
		    
		});
		
		// force repaint - otherwise it has weird artefacts (in chrome at least)
		// code part from: http://stackoverflow.com/a/29946331/1520422
		$set.css('opacity', '.99');
		setTimeout(function() {
			$set.css('opacity', 1);
		}, 20);
		
		return $set;
    
    }

}( jQuery ));

$(document).ready(function() {
	
	cms_hover_init();
	
});