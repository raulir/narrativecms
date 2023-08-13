function shop_basket_init(){

	$('.shop_basket_item_remove').on('click.cms', function(e){
	
		var $this = $(this)
	
		get_ajax_panel(
			'shop/basket', 
			{
				'do': 'remove',
				'item_id': $this.data('item_id'),
				'id': $('.shop_basket_container').data('cms_page_panel_id'),
			}, 
			result => {
				
				$('.shop_basket_container').replaceWith(result.result.html)
				
				if (typeof headerbasket_update == 'function'){
					headerbasket_update()
				}

			}
		)

	})
	
	$('.shop_basket_checkout').on('click.cms', function(e){
	
		var $this = $(this)
	
		get_ajax_panel(
			'shop/checkout', 
			{}, 
			result => {				
				$('.shop_basket_area').html(result.result.html)
			}
		)

	})

}

function shop_basket_resize(){

}

function shop_basket_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		shop_basket_resize();
	});
	
	$(window).on('scroll.cms', function(){
		shop_basket_scroll();
	});
	
	shop_basket_init();

	shop_basket_resize();
	
	shop_basket_scroll();

});
