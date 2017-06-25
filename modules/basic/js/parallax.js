( function( $ ) {
	
    $.fn.basic_parallax = function(params) {
    	
    	var params = $.extend({'parallax_effect': 0.3, 'top': 0 }, params)

    	var $set = $(this);
    	
    	var basic_parallax_scroll = function($this){
    		
    		var page_centre = document.body.scrollTop + (window.innerHeight/2)
			
			var delta = ($this.data('_basic_parallax_object_centre') - page_centre)/_cms_rem;

			$this.css('top', - (delta * $this.data('_basic_parallax_effect')) + 'rem');
    	
    	}
		
		$set.each(function(){

			var $this = $(this);
			
			$this.data('_basic_parallax_effect', ($this.data('parallax_effect') !== false) ? $this.data('parallax_effect') : params.parallax_effect);
			$this.data('_basic_parallax_object_centre', $this.offset().top + ($this.height()/2))

			$(window).on('scroll.basic_parallax', function(){
				basic_parallax_scroll($this);
			});
			
			basic_parallax_scroll($this);
			
		});

		return $set;

    }

}( jQuery ));