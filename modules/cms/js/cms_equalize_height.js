( function( $ ) {
	 
    $.fn.equalize_height = function() {
    	
    	var $set = this;
 
    	$set.css({'height':'', 'min-height':''});

    	for (var i = 0; i <= 9; i++) {

    		$set.each(function(){

    			var $this = $(this);
    			var max_height = 0;
    			var top = $this.offset().top;

    			$set.each(function(){
    				
    				var $that = $(this);
    				if ($that.offset().top == top && $that.height() > max_height){
    					max_height = $that.height();
    				}
    				
    			});
    			
    			if (max_height > 0 && !$this.data('max_height_set')){
    				$this.css({'min-height': Math.ceil(max_height) + 'px'}).data('max_height_set', true);
    			}
    	
    		});
    	
    	}
    	
    	$set.data('max_height_set', false);
 
        return $set;
 
    };
 
}( jQuery ));
