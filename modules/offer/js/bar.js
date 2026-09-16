var offerbar_animating = false

function offerbar_init(){

	var timeout_handler = false

	if (_cms_mobile && $('.offerbar_offer').length > 1){
		offerbar_activate($('.offerbar_offer').first(), 'static')
		timeout_handler = setTimeout(() => $('.offerbar_arrow_right').click(), 5000)
	}

	$('.offerbar_arrow_left').on('click.cms', function(){

		var $next = $('.offerbar_active').prevAll('.offerbar_offer').last()
		if ($next.length == 0){
			$next = $('.offerbar_offer').last()
		}
		offerbar_activate($next, 'left')

		clearTimeout(timeout_handler)
		timeout_handler = setTimeout(() => $('.offerbar_arrow_right').click(), 5000)

	})

	$('.offerbar_arrow_right').on('click.cms', function(){

		var $next = $('.offerbar_active').nextAll('.offerbar_offer').first()
		if ($next.length == 0){
			$next = $('.offerbar_offer').first()
		}
		offerbar_activate($next, 'right')

		clearTimeout(timeout_handler)
		timeout_handler = setTimeout(() => $('.offerbar_arrow_right').click(), 5000)

	})

}

function offerbar_activate($element, mode){

	if (offerbar_animating){
		return
	}

	offerbar_animating = true
	setTimeout(() => {
		offerbar_animating = false
	}, 1500)

	var $offerbar_active = $('.offerbar_active')
	$('.offerbar_active').removeClass('offerbar_active')
	$element.addClass('offerbar_active')

	var $oprev = $offerbar_active.prev()
	var $onext = $offerbar_active.next()
	$oprev.css({'opacity':'0'})
	$onext.css({'opacity':'0'})
	setTimeout(() => {
		$oprev.css({'display':'none', 'left':''})
		$onext.css({'display':'none', 'left':''})
	}, 500)

	if (mode == 'static'){
		$element.css({'opacity':'1', 'left':'50%'})
	} else if (mode == 'right'){
		$offerbar_active.css({'opacity':'0', 'left':'calc(50% - 50.0rem)'})

		$element.css({'display':'none', 'opacity':'0'})
		setTimeout(() => {
			$element.css({'display':'', 'left':'calc(50% + 50.0rem)'})
		}, 50)
		setTimeout(() => {
			$element.css({'left':'50%'})
		}, 100)
		setTimeout(() => {
			$element.css({'opacity':'1'})
		}, 750)
	} else if (mode == 'left'){
		$offerbar_active.css({'opacity':'0', 'left':'calc(50% + 50.0rem)'})

		$element.css({'display':'none', 'opacity':'0'})
		setTimeout(() => {
			$element.css({'display':'', 'left':'calc(50% - 50.0rem)'})
		}, 50)
		setTimeout(() => {
			$element.css({'left':'50%'})
		}, 100)
		setTimeout(() => {
			$element.css({'opacity':'1'})
		}, 750)
	}

	setTimeout(function(){
		var element_w = $element.outerWidth()
		var $prev = $element.prev()
		var prev_w = $prev.outerWidth()
		$prev.css({'display':'block', 'left':('calc(50% - ' + prev_w + 'px - ' + element_w/2 + 'px)'), 'z-index':'10'})
		var $next = $element.next()
		$next.css({'display':'block', 'left':('calc(50% + ' + element_w/2 + 'px)'), 'z-index':'10'})
		setTimeout(function(){
			$prev.css({'opacity':'1'})
			$next.css({'opacity':'1'})
		}, 100)
	}, 1400)

}

function offerbar_resize(){

}

function offerbar_scroll(){

}

$(document).ready(function() {

	$(window).on('resize.cms', offerbar_resize)

	$(window).on('scroll.cms', offerbar_scroll)

	offerbar_init()
	offerbar_resize()
	offerbar_scroll()

})
