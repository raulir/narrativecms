function cms_scroll_lock(){
	
	if ($(document).height() > $(window).height()) {
	     var scrollTop = ($('html').scrollTop()) ? $('html').scrollTop() : $('body').scrollTop()
	     $('html').css({'top':-scrollTop, 'position':'fixed', 'width':'100%'})       
	}

}

function cms_scroll_unlock(){
	
	var scrollTop = parseInt($('html').css('top'))
	$('html').css({'top':'','position':'', 'width':''})
	$('html,body').scrollTop(-scrollTop)

}

/* deprecated */
function lock_scroll(){
	cms_scroll_lock();
}

function cms_lock_scroll(){
	cms_scroll_lock()
}

function unlock_scroll(){
	cms_scroll_unlock()
}

function cms_unlock_scroll(){
	cms_scroll_unlock()
}
