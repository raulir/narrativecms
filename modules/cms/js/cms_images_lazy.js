
( function( $ ) {
	
    $.fn.cms_images_lazy = function(params) {
    	
    	// there is no params, really
    	params = params || {};
    	
    	var $set = $(this);
    	
    	var images = [];
		
		// load hq images, 1st collect images
		$set.each(function(){
			
			var $this = $(this);
			
			if (!($this.hasClass('cms_images_lazy_init'))){
				
				$this.addClass('cms_images_lazy_init').addClass('cms_images_lazy_waiting');

			}
			
		});
		
		let cms_images_lazy_interval = setInterval(function(){
			if ($('.cms_images_lazy_loading').length < 1){
				let $next = $('.cms_images_lazy_waiting').first();
				if ($next.length){
					$next.addClass('cms_images_lazy_loading').removeClass('cms_images_lazy_waiting');
					cms_images_lazy_next($next);
				} else {
					clearInterval(cms_images_lazy_interval);
				}
			}
		}, 500);
		

    };
    
}( jQuery ));

function cms_images_lazy_next($this){
	
	get_api('cms/image_resize', {
		'do':'resize',
		'width': $this.data('width'),
		'output': $this.data('output'),
		'name': $this.data('cms_images_lazy'),
		'success': function(data){
			
			var hq_src = data.result.src;

			setTimeout(function(){
				
				// lq width
				get_api('cms/image_resize', {
					'do':'resize',
					'width': $this.data('width_lq'),
					'output': $this.data('output'),
					'name': $this.data('cms_images_lazy'),
					'success': function(data){
						
						$this.css({'background-image': 'url(' + hq_src + '?v=' + Math.floor(Date.now() / 1000) + ')'});
						$this.addClass('cms_images_lazy_done');
						$this.removeClass('cms_images_lazy_loading');
					
					}
				})
				
			}, 250);
			
		}
	})
	
}

function cms_images_lazy_init(){
	
	// delay to be sure, that lq images get loaded first
	setTimeout(function(){
		
		$('[data-cms_images_lazy]').cms_images_lazy();

	}, 100);

}

$(document).ready(function() {
	
	cms_images_lazy_init();
	
});