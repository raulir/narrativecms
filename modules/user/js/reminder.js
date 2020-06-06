function reminder_init(){
	
$('.reminder_submit').on('click.cms', function(){
		
		var data = {
				'do': 'remind',
				'username': $('.reminder_input_username').val(),
				'url': window.location.href
		}

		data.success = function(result){

			if (result.result.error){
				
				reminder_show_error(result.result.error)
				
			} else {
				
				reminder_show_success()
				
			}
		}

		$('.reminder_error_active').removeClass('reminder_error_active')
		
		get_ajax('user/reminder', data);

	})
	
	$('.reminder_save').on('click.cms', function(){
		
		var data = {
				'do': 'save',
				'username': $('.reminder_input_username').val(),
				'password': $('.reminder_input_password').val(),
				'password2': $('.reminder_input_password2').val(),
				'token': $('.reminder_save').data('token')
		}

		data.success = function(result){

			if (result.result.error){
				
				reminder_show_error(result.result.error)
				
			} else {
				
				$('.reminder_error_active').removeClass('reminder_error_active')
				$('.reminder_save_success').addClass('reminder_success_active')
				
			}
		}

		$('.reminder_error_active').removeClass('reminder_error_active')
		
		get_ajax('user/reminder', data);

	})

}

function reminder_show_error(error){
	
	$('.reminder_error_active').removeClass('reminder_error_active')
	$('.reminder_error_' + error).addClass('reminder_error_active')
	
}

function reminder_show_success(){
	
	$('.reminder_error_active').removeClass('reminder_error_active')
	$('.reminder_success').addClass('reminder_success_active')
	
}

function reminder_resize(){
	
}

function reminder_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		reminder_resize();
	});
	
	$(window).on('scroll.cms', function(){
		reminder_scroll();
	});
	
	reminder_init();

	reminder_resize();
	
	reminder_scroll();

});
