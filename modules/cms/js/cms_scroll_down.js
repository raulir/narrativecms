// scroll to next cms container
// parent must have "cms_container"

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
