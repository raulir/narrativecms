function onetrust_init(){

	setInterval(() => {
		$('#ot-sdk-btn').html($('.onetrust_container').data('settings_label'))
		$('.cookie-setting-link').html($('.onetrust_container').data('settings_label'))
	}, 500)
	
}

function onetrust_resize(){
		
}

function onetrust_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', onetrust_resize)
	
	$(window).on('scroll.cms', onetrust_scroll)
	
	onetrust_init()
	onetrust_resize()
	onetrust_scroll()
	
})
