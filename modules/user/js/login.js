function login_init(){
	
	$('.login_submit').on('click.cms', function(){
		
		var data = {
				'do': 'login',
				'username': $('.login_input_username').val(),
				'password': $('.login_input_password').val()
		}

		data.success = function(result){

			if (result.result.error){
				
				login_show_error(result.result.error)
				
			} else {
				
				// redirect to success url
				if ($('.login_container').data('success')){
					window.location.href = $('.login_container').data('success')
				} else {
					alert('login successful')
				}
				
			}
		}

		$('.login_error_active').removeClass('login_error_active')
		
		get_ajax('user/login', data);

	})

}

function login_show_error(error){
	
	$('.login_error_active').removeClass('login_error_active')
	$('.login_error_' + error).addClass('login_error_active')
	
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
