function article_init(){

	$('.feature_block_video').each(function(){

		var $this = $(this);
		
		$('iframe', this).attr({'height': $this.outerHeight(), 'width': $this.width()}).css({'display':'block'});
		
	});

    $('.article_arrow').off('click.r').on('click.r', function(){
        $('html, body').animate({ scrollTop: $(this).parent().offset().top + $(this).parent().height() + 'px' }, 800);
    });
	
}

function article_resize(){

	// nothing to do
	
}

$(document).ready(function() {
	
	/*
	$(window).on('resize.r', function(){
		article_comments_resize();
	});
	
	setTimeout(article_comments_resize, 1000);
	article_comments_resize();
	*/
	
	article_init();
	
});