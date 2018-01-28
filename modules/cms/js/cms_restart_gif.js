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