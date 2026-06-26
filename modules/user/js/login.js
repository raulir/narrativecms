function login_init(){
	
	$('.login_submit').on('click.cms', function(){
		
		var data = {
				'do': 'login',
				'username': $('.login_input_username').val(),
				'password': $('.login_input_password').val()
		}

		var cms_page_panel_id = $('.login_container').data('cms_page_panel_id')
		if (cms_page_panel_id){
			data.cms_page_panel_id = cms_page_panel_id
		}

		data.success = function(result){

			if (result.result.error){
				
				login_show_error(result.result.error)
				
			} else {
				
				login_show_success()
				
			}
		}

		$('.login_error_active').removeClass('login_error_active')
		
		get_ajax('user/login', data);

	})
	
	if ($('.login_app_google').length){
		$('.login_app_google').on('click.cms', google_login)
	}
	
	$('.login_resend_verification').on('click.cms', function(){
		
		var data = {
				'do': 'resend_verification',
				'username': $('.login_input_username').val(),
		}
		
		var cms_page_panel_id = $('.login_container').data('cms_page_panel_id')
		if (cms_page_panel_id){
			data.cms_page_panel_id = cms_page_panel_id
		}
		
		get_ajax('user/login', data)
		
	})

}

function login_show_error(error){
	
	$('.login_error_active').removeClass('login_error_active')
	$('.login_error_' + error).addClass('login_error_active')
	
}

function login_show_success(){
	
	$('.login_error_active').removeClass('login_error_active')
	window.location.href = $('.login_container').data('success')
	
}

function login_resize(){
	
}

function login_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		login_resize();
	});
	
	$(window).on('scroll.cms', function(){
		login_scroll();
	});
	
	login_init();

	login_resize();
	
	login_scroll();

});
