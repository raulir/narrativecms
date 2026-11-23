function sidefloat_init(){
	
	// place after cms_header
	$('.sidefloat_container').css({'top': $('.cms_header').height()}).addClass('sidefloat_active');
	
}


function sidefloat_add(element_id, $element, params){
	
	var params = $.extend({'delay': 0, 'close': false }, params);

	$('.sidefloat_area').append('<div class="sidefloat_item sidefloat_item_hidden sidefloat_item_' + element_id +'"></div>');
	
	var $sidefloat_item = $('.sidefloat_item_' + element_id);
	
	$element.detach().appendTo($sidefloat_item);
	
	if (params.close){
		
		$sidefloat_item.append('<div class="sidefloat_item_close"></div>');
		
		$('.sidefloat_item_close', $sidefloat_item).on('click.cms', function(){
			
			var $this = $(this);
			
			$this.closest('.sidefloat_item').addClass('sidefloat_item_hidden');
			setTimeout(function(){
				$this.remove();
			}, 1000);
			
		});

	}
	
	setTimeout(function(){
		
		$sidefloat_item.removeClass('sidefloat_item_hidden');
		
	}, params.delay);
	
}

$(document).ready(function() {

	sidefloat_init();

});
