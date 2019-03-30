function cms_language_select_init(){
	
	$('.cms_language_select_option').on('click.cms', function(){

		var $this = $(this);
		
		get_ajax('cms/cms_language_operations', {
			'do':'cms_language_set',
			'language': $this.data('language'),
			'success': function(){
				location.reload();
			}
		})

	});

}

function cms_language_select_resize(){
	
}

function cms_language_select_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_language_select_resize();
	});
	
	$(window).on('scroll.cms', function(){
		cms_language_select_scroll();
	});
	
	cms_language_select_init();

	cms_language_select_resize();
	
	cms_language_select_scroll();

});
