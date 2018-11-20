// scroll to next cms container
// parent must have "cms_container"

( function( $ ) {
	
    $.fn.cms_scroll_down = function(params) {
    	
    	params = $.extend({'steps': 1, 'success': function(){} }, params);
    	
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
    				
    				var $container = $this.closest('.cms_container');
    				if (!$container.length){
    					$container = $this;
    				}
    				
    				var position = $container.offset().top + $container.outerHeight();
    				
    				var header_height = 0;
    				var i = params.steps;
    				
    				var interval_f = function(){
    					
    					if (i == 0){
    						clearInterval(interval);
    						return;
    					}
        				
    					if ($header.length){
        					header_height = $header.height();
        				}
        				
        				$('html, body').stop().animate({
        					scrollTop: position - header_height
        				}, 500/params.steps);
        				
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
