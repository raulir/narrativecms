function language_init(){

	$('.language_language').on('click.cms', function(){

		var $this = $(this);
		
		cms_cookie_create('language', $this.data('language_id'), 365)
		
		get_ajax('basic/language', {
			'do':'language_set',
			'language_id': $this.data('language_id'),
			'success': function(){
				location.reload();
			}
		})

	});

}

function language_resize(){

}

function language_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		language_resize();
	});
	
	$(window).on('scroll.cms', function(){
		language_scroll();
	});
	
	language_init();

	language_resize();
	
	language_scroll();

});
