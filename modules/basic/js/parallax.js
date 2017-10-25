function basic_parallax_scroll($this, params){
	
	var scrolltop = self.pageYOffset || document.documentElement.scrollTop  || document.body.scrollTop;
	var top = basic_parallax_get_top(scrolltop, $this);
	
	if (!$this.hasClass('_basic_parallax_disabled')){
		$this.css('top', top + 'rem').data('_basic_parallax_top', top);
		params.after({'$this': $this, 'top': top + 'rem'});
	} else {
		params.after({'$this': $this, 'top': ''});
	}
	
}

function basic_parallax_get_top(scrollx, $this){
	
	var page_centre = scrollx + (window.innerHeight * $this.data('_basic_parallax_params').parallax_base)
	var delta = ($this.data('_basic_parallax_object_centre') - page_centre)/_cms_rem;
	var top = - (delta * $this.data('_basic_parallax_effect')) + $this.data('_basic_parallax_params_top');

	return top;

}

( function( $ ) {
	
    $.fn.basic_parallax = function(params) {
    	
    	var params = $.extend({'parallax_effect': 0.3, 'parallax_base': 0.5, 'top': 0, 'above_base_fix': false, 'after': function(data){} }, params);

    	var $set = $(this);

    	
		$set.each(function(){

			var $this = $(this);
			
			$this.data('_basic_parallax_effect', (typeof $this.data('parallax_effect') !== 'undefined') ? $this.data('parallax_effect') : params.parallax_effect);
			$this.data('_basic_parallax_object_centre', $this.offset().top + ($this.height() * params.parallax_base));
			$this.data('_basic_parallax_params_top', params.top);
			$this.data('_basic_parallax_params', params);
			
			// if above the fold/base fix, add extra top
			if (params.above_base_fix){
				
				if (!$this.data('above_base_fix')){
					
					$this.data('above_base_fix', true);

					var window_centre = window.innerHeight * $this.data('_basic_parallax_params').parallax_base;
					
					if ($this.data('_basic_parallax_object_centre') < window_centre){
						
						var delta = (($this.data('_basic_parallax_object_centre') - window_centre)/_cms_rem) * $this.data('_basic_parallax_effect');
						$this.data('_basic_parallax_params_top', $this.data('_basic_parallax_params_top') + delta);
						
					}
					
				}
				
			}

			$(window).on('scroll.basic_parallax', function(){
				if (!$this.hasClass('_basic_parallax_freeze')){
					basic_parallax_scroll($this, params);
				}
			});
			
			$(window).on('resize.basic_parallax', function(){
				$this._basic_parallax_resize();
			});
			
			basic_parallax_scroll($this, params);
			
		});

		return $set;

    }
    
    $.fn._basic_parallax_resize = function(params) {
    	
    	var params = $.extend({'parallax_base': 0.5}, params)

    	var $set = $(this);
    	
    	$set.each(function(){
    	
    		var $this = $(this);
    		
    		if (!$this.hasClass('_basic_parallax_freeze')){
    		
    			$this.addClass('_basic_parallax_freeze');
    			
    			$this.css({'top':'', 'opacity': 0});
    			
    			setTimeout(function(){
    				
    				$this.data('_basic_parallax_object_centre', $this.offset().top + ($this.height() * params.parallax_base));
    				$this.removeClass('_basic_parallax_freeze');
    				$this.css({'opacity': ''});
    				basic_parallax_scroll($this, $this.data('_basic_parallax_params'));
    			
    			}, 30);

    		}
    
    	});
    	
    	return $set;
    
    }
    	
}( jQuery ));