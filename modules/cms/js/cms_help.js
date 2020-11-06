function cms_help_init(){
	
	$('.cms_help').each(function(){
		
		if ($(this).hasClass('cms_help_ok')){
			return
		}
		
		$(this).addClass('cms_help_ok')
		
		var $clone;
		
		$(this).on('mouseenter.cms', function(){
			$clone = $('.cms_help_text', $(this)).clone().css({
				'left': $(this).offset().left / _cms_rem + 'rem',
				'top': ($(this).offset().top / _cms_rem) + 1.6 + 'rem',
				'display': 'block'
			}).appendTo('body')
		})
		
		$(this).on('mouseleave.cms', function(){
			$clone.remove()
		})
		
	})

}

function cms_help_resize(){
	
}

function cms_help_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', cms_help_resize)
	$(window).on('scroll.cms', cms_help_scroll)
	
	cms_help_init()
	cms_help_resize()
	cms_help_scroll()

})
