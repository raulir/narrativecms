// use cms scroll to
( function( $ ) {
	
    $.fn.cms_scroll_top = function(params) {

    	params = params || {'speed':800, 'after': function(){}};
    	
    	$(this).each(function(){
    		
    		var $this = $(this);
    		
    		if ($this.data('cms_scroll_top') != '1'){

    			$(this).on('click.cms', function(){
	    			$('html, body').animate({ 
	    				scrollTop: 0
	    			}, params.speed)
	    			params.after()
	    		})
	    		
    			$this.data('cms_scroll_top', '1');

    		}

    	});
    	
    };
    
}( jQuery ));
