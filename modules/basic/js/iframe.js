function iframe_init(){
	
	$('.basic_iframe_iframe').each(function(){
		setTimeout(() => {
			if (iFrameResize){
				iFrameResize({ log: true }, ('#'+$(this).attr('id')))
			}
		}, $(this).data('delay'))
	})

}

function iframe_resize(){

}

function iframe_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', iframe_resize)
	$(window).on('scroll.cms', iframe_scroll)

	iframe_init()
	iframe_resize()
	iframe_scroll()

})
