
( function( $ ) {
	
    $.fn.cms_window_height = function(params) {
    	
    	// there is no params, really
    	params = params || {};
    	
    	var $set = $(this);

    	// init set
		$set.each(function(){
			
			var $this = $(this);
			if ($this.data('cms_wh_ok') != '1' && $this.data('cms_window_height')){
				
				$this.addClass('cms_window_height');
				
				$this.data('cms_wh_ok', '1');

			}
			
		});
		
		function cms_window_height_get(){

			$('body')
					.append('<div id="get_height_top" style="position: fixed; top: 0; height: 0; overflow: hidden; "></div>')
					.append('<div id="get_height_bottom" style="position: fixed; bottom: 0; height: 0; overflow: hidden; "></div>');

			var top = $('#get_height_top').position().top;
			var bottom = $('#get_height_bottom').position().top;

			$('#get_height_top').remove();
			$('#get_height_bottom').remove();

			var ret = bottom - top;

			return ret;

		}

		// calculate height for all the elements
		function cms_window_height_apply(){
			
			var $set = $('.cms_window_height');
			
			var height = cms_window_height_get();
			
			var scroll = document.documentElement.scrollTop + $(window).height()/2
			
			$set.each(function(){
				
				var $this = $(this);
				
				if (_cms_mobile){
					
					var new_height = Math.round(height * $this.data('cms_window_height')) / 100;
					if ($this.data('cms_window_height_current') != new_height){
						
						var top = $this.offset().top
						
						// check if element is in viewport
						if (scroll > top && scroll < (top + $this.height())){
						
							$this.data('cms_window_height_current', new_height);
							$this.height(new_height);
							
						}
					
					}

				} else {
					
					$this.css({'height':''});
				
				}

			});
			
			return $set;
			
		}
		
		$(window).off('resize.cms_window_height').on('resize.cms_window_height', function(){
			cms_window_height_apply();
		});

		$(window).off('scroll.cms_window_height').on('scroll.cms_window_height', function(){
			cms_window_height_apply();
		});
		
		cms_window_height_apply();
		
		return $set;
		
    };
    
}( jQuery ));

function cms_window_height_init(){
	
	// delay to be sure
	setTimeout(function(){
		
		$('[data-cms_window_height]').cms_window_height();

	}, 100);

}

$(document).ready(function() {
	
	cms_window_height_init();
	
});