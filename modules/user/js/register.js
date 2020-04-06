function register_init(){
	
	$('.register_submit').on('click.cms', function(){
		
		var data = {
				'do': 'register',
				'email': $('.register_email').val(),
				'phone': $('.register_phone').val(),
				'first_name': $('.register_first_name').val(),
				'last_name': $('.register_last_name').val()
		}
		
		if ($('.register_username').length){
			data['username'] = $('.register_username').val()
		}

		if ($('.register_password').length){
			
			data['password'] = $('.register_password').val()
			data['password2'] = $('.register_password2').val()
			
			if (data['password'] == ''){
				register_show_error('password_missing')
				return
			}
			
		}
		
		data.success = function(result){

			if (result.result.error){
				
				register_show_error(result.result.error)
				
			} else {
				
				// redirect to success url
				if ($('.register_container').data('success')){
					window.location.href = $('.register_container').data('success')
				} else {
					alert('registration successful')
				}
				
			}
		}

		$('.register_error_active').removeClass('register_error_active')
		
		get_ajax('user/register', data);

	})

}

function register_show_error(error){
	
	$('.register_error_active').removeClass('register_error_active')
	$('.register_error_' + error).addClass('register_error_active')
	
}

function register_resize(){
	
}

function register_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		register_resize();
	});
	
	$(window).on('scroll.cms', function(){
		register_scroll();
	});
	
	register_init();

	register_resize();
	
	register_scroll();

});
