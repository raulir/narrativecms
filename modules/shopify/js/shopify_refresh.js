function shopify_refresh_init(){
	
	$('.shopify_refresh').on('click.cms', function(event){
		$('.cms_list_list_container').css({'opacity':'0.5'})
		get_ajax_panel('shopify/shopify_refresh', {
			'do': 'refresh' 
		}, function(data){
			location.reload()
		})
	})

}

function shopify_refresh_resize(){
		
}

function shopify_refresh_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', shopify_refresh_resize)
	$(window).on('scroll.cms', shopify_refresh_scroll)
	
	shopify_refresh_init()
	shopify_refresh_resize()
	shopify_refresh_scroll()
	
})
