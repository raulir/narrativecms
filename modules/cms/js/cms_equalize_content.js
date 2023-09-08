( function( $ ) {
	
	// params.step = percentage to shrink at each try
    $.fn.cms_equalize_content = function(params) {
    	
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
