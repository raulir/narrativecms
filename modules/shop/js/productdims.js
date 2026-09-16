function productdims_init(){

	$('.shop_productdims_dim_value').off('click.cms').on('click.cms', function(e){

		$(this).closest('.shop_productdims_dim').children('.shop_productdims_dim_input').val($(this).data('value'))

		$(this).siblings().removeClass('shop_productdims_dim_value_active')
		$(this).addClass('shop_productdims_dim_value_active')
		
		if ($('.shop_productdims_dim').length > 1){
			
			var data = {}
			
			data['do'] = 'availability'
			data['product_id'] = $('.shop_productdims_container').data('product_id')
			data['name'] = $(this).data('name')
			data['value'] = $(this).data('value')

			get_ajax_panel('shop/productdims', data, function(data){
				
				$('.shop_productdims_message').html('')
				
				$('.shop_productdims_dim_value_active').each(function(){
					if (typeof data.data[$(this).data('name')][$(this).data('value')] == 'undefined'){
						$(this).removeClass('shop_productdims_dim_value_active')
						$(this).closest('.shop_productdims_dim').children('.shop_productdims_dim_input').val('')
					}
				})
				
			})
			
		}

	})

}

function productdims_resize(){

}

function productdims_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		productdims_resize();
	});
	
	$(window).on('scroll.cms', function(){
		productdims_scroll();
	});
	
	productdims_init();

	productdims_resize();
	
	productdims_scroll();

});
