
( function( $ ) {
	
    $.fn.cms_hq_background = function(params) {
    	
    	// there is no params, really
    	params = params || {};
    	
    	var $set = $(this);
		
		// load hq images, 1st collect images
		var images = [];
		$set.each(function(){
			
			var $this = $(this);
			if ($this.data('cms_hq_ok') != '1' && $this.data('cms_hq_background')){
				var image = $this.data('cms_hq_background');
				images.push(image);
				$this.data('cms_hq_ok', '1');
			}
			
		});
		
		cms_preloader.preload({
			'images': images,
			'after_each': function(image){
				$set.each(function(){
					var $this = $(this);
					if ($this.data('cms_hq_background') == $(image).attr('src')){
						$this.css({'background-image': 'url(' + image.src + ')'});
					}
				});
			}
		});

    };
    
}( jQuery ));

function cms_images_hq_init(){
	
	// delay to be sure, that lq images get loaded first
	setTimeout(function(){
		
		$('[data-cms_hq_background]').cms_hq_background();

	}, 100);

}

$(document).ready(function() {
	
	cms_images_hq_init();
	
});