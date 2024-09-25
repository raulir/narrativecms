function shopify_productrefresh_init(){
	
	$('.shopify_productrefresh').on('click.cms', function(event){
		$('.cms_page_panel_container').css({'opacity':'0.5'})
		get_ajax_panel('shopify/shopify_productrefresh', {
			'do': 'refresh',
			'product_id': $(this).data('product_id')
		}, function(data){
			location.reload()
		})
	})

}

function shopify_productrefresh_resize(){
		
}

function shopify_productrefresh_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', shopify_productrefresh_resize)
	$(window).on('scroll.cms', shopify_productrefresh_scroll)
	
	shopify_productrefresh_init()
	shopify_productrefresh_resize()
	shopify_productrefresh_scroll()
	
})
