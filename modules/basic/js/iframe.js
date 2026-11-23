function iframe_init(){
	
	$('.basic_iframe_iframe').each(function(){
		setTimeout(() => {
			if (iFrameResize){
				iFrameResize({ log: true }, ('#'+$(this).attr('id')))
			}
		}, $(this).data('delay'))
	})

}


$(document).ready(function() {

	iframe_init()

})
