function popup_init(){

	$('.gdpr_popup_container').not('.popup_ok').each(function(){

		var $popup = $(this)
		$popup.addClass('popup_ok')

		$popup.find('.gdpr_popup_upper').on('click.cms', function(){

			if (!$('.gdpr_popup_active').length){
				$('.gdpr_popup_container').addClass('gdpr_popup_active');
			} else {
				$('.gdpr_popup_active').removeClass('gdpr_popup_active');
			}

		})

		$popup.find('.gdpr_popup_close').on('click.cms', function(){

			$('.gdpr_popup_container').remove();

			cms_cookie_create('gdpr', 1, 180);

		})

		$popup.find('.gdpr_popup_notracking').on('click.cms', function(){

			$('.gdpr_popup_container').remove();

			cms_cookie_create('gdpr', 1, 7);
			cms_cookie_create('gdpr_notrack', 1, 7);

		})

	})

}


$(document).ready(function() {
	
	popup_init();
	
});
