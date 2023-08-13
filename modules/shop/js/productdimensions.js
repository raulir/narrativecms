function productdimensions_init(){

	$('.shop_productdimensions_dimension_value').off('click.cms').on('click.cms', function(e){

		$(this).closest('.shop_productdimensions_dimension').children('.shop_productdimensions_dimension_input').val($(this).data('value'))

		$(this).siblings().removeClass('productdimensions_dimension_value_active')
		$(this).addClass('productdimensions_dimension_value_active')
		
		// if more than one dimension check availability in other dimensions
		if ($('.shop_productdimensions_dimension').length > 1){
			
			var data = {}
			
			data['do'] = 'availability'
			data['product_id'] = $('.shop_productdimensions_container').data('product_id')
			data['name'] = $(this).data('name')
			data['value'] = $(this).data('value')

			get_ajax_panel('shop/productdimensions', data, function(data){
				
				$('.shop_productdimensions_message').html('')
				
				$('.shop_productdimensions_dimension_value_active').each(function(){
					if (typeof data.data[$(this).data('name')][$(this).data('value')] == 'undefined'){
						$(this).removeClass('productdimensions_dimension_value_active')
						$(this).closest('.shop_productdimensions_dimension').children('.shop_productdimensions_dimension_input').val('')
						
						/* TODO: warning about removal
						if ($('.shop_productdimensions_message').html() == ''){
							$('.shop_productdimensions_message').html()
						}
						
						$('.shop_productdimensions_message').html($('.shop_productdimensions_message').html() + ' ' + )
						*/
						
					}
				})
				
			})
			
		}

	})

}

function productdimensions_resize(){

}

function productdimensions_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		productdimensions_resize();
	});
	
	$(window).on('scroll.cms', function(){
		productdimensions_scroll();
	});
	
	productdimensions_init();

	productdimensions_resize();
	
	productdimensions_scroll();

});
