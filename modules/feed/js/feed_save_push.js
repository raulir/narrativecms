function feed_save_push_init(){

	$('.feed_save_push_button').on('click.r', function(event){
		var $button = $(this);
		$button.css({'opacity':'0.3'});
		get_ajax('feed_save_push', {
			'do': 'feed_save_push',
			'cms_page_panel_id': $('[name="block_id"]').val(),
			'success': function(){
				$button.css({'opacity':'1'});
			}
		});
	});
	
}

function feed_save_push_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		feed_save_push_resize();
	});
	
	feed_save_push_init();

	feed_save_push_resize();
	
});
