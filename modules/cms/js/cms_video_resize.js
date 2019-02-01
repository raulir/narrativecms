( function( $ ) {
	
    $.fn.cms_video_resize = function(params) {

    	params = params || {};

    	if (typeof params.after != 'function'){
    		params.after = function(){};
    	}
    	
    	if (typeof params.fit === 'undefined'){
    		params.fit = 'cover';
    	}

    	if (typeof params.retry === 'undefined'){
    		params.retry = 10;
    	}
    	
    	/* 
    	 * resizing function itself
    	 * 
    	 */
    	var cms_video_resize_helper = function($target, params){
    		
    		if ($target.data('retry')){
    			$target.data('retry', $target.data('retry') + 1);
    		} else {
    			$target.data('retry', 1);
    		}

    		$target.css({
    			'width': '',
    			'height': ''
    		});
    		
    		var video_width = $target.width();
    		var video_height = $target.height();
    		
    		if ($target.data('retry') <= params.retry && ($target[0].readyState !== 4 || video_width == 0 || video_height == 0)){

    			setTimeout(function(){
    				cms_video_resize_helper($target, params);
    			}, 500);
    			return;
    		
    		}

    		var $parent = $target.parent();
    			
    		var parent_width = $parent.width();
    		var parent_height = $parent.height();
    		
    		var video_ratio = video_width/video_height;
    		var parent_ratio = parent_width/parent_height;

    		if(params.fit === 'cover'){

        		if (video_ratio > parent_ratio){
        			video_height = parent_height;
        			video_width = video_height * video_ratio;
        		} else {
        			video_width = parent_width;
        			video_height = video_width / video_ratio;
        		}

        		$target.css({
        			'width': (video_width/parent_width)*100 + '%',
        			'height': (video_height/parent_height)*100 + '%',
        			'top': - (video_height - parent_height)/(2*parent_height)*100 + '%',
        			'left': - (video_width - parent_width)/(2*parent_width)*100 + '%'
        		});

    		} else {

        		if (video_ratio < parent_ratio){
        			video_height = parent_height;
        			video_width = video_height * video_ratio;
        		} else {
        			video_width = parent_width;
        			video_height = video_width / video_ratio;
        		}

        		$target.css({
        			'width': (video_width/parent_width)*100 + '%',
        			'height': (video_height/parent_height)*100 + '%',
        			'top': - (video_height - parent_height)/(2*parent_height)*100 + '%',
        			'left': - (video_width - parent_width)/(2*parent_width)*100 + '%'
        		});

    		}

    		params.after($target);

    	}

    	var $set = $(this);
		
		$set.each(function(){

			cms_video_resize_helper($(this), params);
		    
		});
		
		return $set;
    
    }

}( jQuery ));
