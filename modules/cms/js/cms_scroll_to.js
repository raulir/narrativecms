( function( $ ) {
	
    $.fn.cms_scroll_to = function(params) {
    	
    	params = params || {};

    	var $set = $(this);
		
		$set.each(function(){
			
			var $this = $(this);
			
			if (!$this.data('cms_scroll_to_init')){
				
				$this.data('cms_scroll_to_init', true);
				
				$this.on('click.cms', function(e){
					e.stopPropagation();
					
					var $this = $(this);
					
					var delta = 0;
					if (params.$space === false){
						delta = 0;
					} else if (params.$space){
						delta = params.$space.height();
					} else if ($('.cms_header').length){
						delta = $('.cms_header').height();
					}

					if ($this.attr('href')){
						var name = $this.attr('href').replace('#', '');
					} else if ($this.data('cms_scroll_to')) {
						var name = $this.data('cms_scroll_to').replace('#', '');
					} else {
						var name = '_top';
					}
					
					if (name && $('#' + name).length){
					
						var elementtop = $('#' + name).offset().top;
					
						$('html, body').animate({ scrollTop: elementtop - delta }, 800);
					
					} else if (name == '_top'){
						
						$('html, body').animate({ scrollTop: 0 }, 800);
						
					}
					
					$(window).scroll();
					
					return false;
				});
			
			}
			
		});
    
    }
    
}( jQuery ));
