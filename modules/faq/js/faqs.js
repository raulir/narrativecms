function news_init(){
	
	$('.faqs_faq_heading').on('click.cms', function(){
		
		var $faq = $(this).closest('.faqs_faq')
		
		if ($faq.hasClass('faqs_faq_active')){
			$('.faqs_faq_active').removeClass('faqs_faq_active')
		} else {
			$('.faqs_faq_active').removeClass('faqs_faq_active')
			$faq.addClass('faqs_faq_active')
		}

/*
		var interval = setInterval(function(){
			$('html, body').animate({ scrollTop: $faq.offset().top - $('.cms_header').outerHeight() }, 60, 'linear');
		}, 60)
		
		setTimeout(function(){
			clearInterval(interval)
		}, 700)		
*/

	})
	
}

function news_resize(){
	
}

function news_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		news_resize();
	});
	
	$(window).on('scroll.cms', function(){
		news_scroll();
	});
	
	news_init();

	news_resize();
	
	news_scroll();

});
