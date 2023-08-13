function productbuy_init(){

	$('.shop_productbuy_add').off('click.cms').on('click.cms', function(){
		
		var data = {}
		
		$('.shop_productbuy_input', $(this).closest('.shop_productbuy_container')).each((i, el) => {
			data[$(el).attr('name')] = $(el).val()
		})
		
		data['do'] = 'add'

		get_ajax_panel('shop/productbuy', data, function(data){
			
			$('.shop_productbuy_container').parent().html(data.result.html)
			
			if (typeof headerbasket_update == 'function'){
				headerbasket_update()
			}
			
		})

	})
	
}

function productbuy_resize(){

}

function productbuy_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		productbuy_resize();
	});
	
	$(window).on('scroll.cms', function(){
		productbuy_scroll();
	});
	
	productbuy_init();

	productbuy_resize();
	
	productbuy_scroll();

});
