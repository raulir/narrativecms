function language_close_dropdown($button){

	if ($button && $button.length){
		$button.addClass('language_dropdown_closed')
	}

}

function language_init(){

	$('.language_button').on('mouseenter.cms', function(){
		$(this).removeClass('language_dropdown_closed')
	})

	$('.language_language').on('click.cms', function(){

		var $this = $(this)
		var $button = $this.closest('.language_button')

		language_close_dropdown($button)
		
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
