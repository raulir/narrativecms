function cms_scroll_lock(){
	
	if ($(document).height() > $(window).height()) {
	     var scrollTop = ($('html').scrollTop()) ? $('html').scrollTop() : $('body').scrollTop()
	     $('html').css({'top':-scrollTop, 'position':'fixed', 'width':'100%'})       
	}
	$('body').css({'overflow':'hidden'})

}

function cms_scroll_unlock(){
	
	var scrollTop = parseInt($('html').css('top'))
	$('html').css({'top':'','position':'', 'width':''})
	$('html,body').scrollTop(-scrollTop)
	$('body').css({'overflow':''})

}
