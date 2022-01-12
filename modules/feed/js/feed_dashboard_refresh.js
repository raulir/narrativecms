function feed_dashboard_refresh_init(){

	$('.feed_dashboard_refresh_button').on('click.r', function(){
		var $button = $(this);
		$button.css({'opacity':'0.3'});
		get_ajax('feed/feed_dashboard_refresh', {
			'do': 'feed_dashboard_refresh',
			'success': function(data){
				
				cms_list_load($('.cms_list_container').data('start'), $('.cms_list_container').data('limit'), function(){
					$button.css({'opacity':'1'});
				});

				if (data.result.feed_error){
					cms_notification(data.result.feed_error, 10, 'error')
				}
				
			}
		});
	});
	
}

function feed_dashboard_refresh_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		feed_dashboard_refresh_resize();
	});
	
	feed_dashboard_refresh_init();

	feed_dashboard_refresh_resize();
	
});
