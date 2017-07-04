( function( $ ) {
	
    $.fn.cms_keep_ratio = function(params) {
    	
    	params = params || {};

    	var $set = $(this);
		
		$set.each(function(){
			
			var $this = $(this);
			
			if ($this.hasClass('cms_keep_ratio_init')){
				return;
			}
			
			$this.addClass('cms_keep_ratio_init');
			
			if (!$this.data('keep_width') && !$this.data('keep_height')){
				$this.data({'keep_width':100});
			}
			
			if ($this.data('keep_width')){
				
				// simple by padding
				$this.css({
					'padding-bottom': ($this.data('height')/$this.data('width')) * $this.data('keep_width') + '%',
					'width': $this.data('keep_width') + '%',
					'height': '0',
					'box-sizing': 'content-box'
				});
				
			} else {
				
				// needs resizing
				var cms_keep_ratio_resize_height = function($this){
					$this.css({
						'height': $this.data('keep_height') + '%',
						'width': (( $this.data('width') / $this.data('height') ) * ($this.data('keep_height')/100) * $this.parent().height() ) + 'px',
						'box-sizing': 'content-box'
					});
				}
				
				$(window).on('resize.cms_keep_ratio', function(){
					cms_keep_ratio_resize_height($this);
				});
				
				cms_keep_ratio_resize_height($this);

			}
			
		});
    
    }
    
}( jQuery ));
