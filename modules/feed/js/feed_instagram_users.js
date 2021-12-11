function feed_instagram_users_init(){
	
	location.hash = ''

	$('.feed_instagram_user_button').on('click.cms', function(){
		
		$('.feed_instagram_popup_overlay').css({'display':'table'});
		setTimeout(function(){
			$('.feed_instagram_popup_overlay').css({'opacity':'1'});
		}, 50);

	});
	
	$('.feed_instagram_popup_cancel').on('click.cms', function(){
		$('.feed_instagram_popup_overlay').css({'opacity':''});
		setTimeout(function(){
			$('.feed_instagram_popup_overlay').css({'display':'none'});
			$('.feed_instagram_popup_iframe').attr('src', '');
		}, 300);
	});
	
	$('.feed_instagram_popup_ok').on('click.cms', function(){
		
		var uri = window.location.toString()
		if (uri.indexOf('#') > 0) {
            uri = uri.substring(0, uri.indexOf('#'))
		}
		
		window.location = 'https://www.bytecrackers.com/_feed/instagram.php?src=' + encodeURIComponent(uri)
		
	})
	
	$('.feed_instagram_remove_button').on('click.cms', function(){
		get_ajax('feed/feed_instagram_users', {
			'do': 'feed_instagram_remove',
			'cms_page_panel_id': $(this).data('cms_page_panel_id'),
			'success': function(){
				cms_notification('Instagram user removed', 3);
				window.location.reload();
			}
		});
	});
	
}

function feed_instagram_users_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		feed_instagram_users_resize();
	});
	
	feed_instagram_users_init();

	feed_instagram_users_resize();
	
});
