function loginlink_init(){
	
	$('.loginlink_button_create').on('click.cms', function(){
		
		var $input = $(this).closest('.user_input_loginlink')
		
		var data = {
				'do': 'create',
				'user_id': $('.cms_page_panel_id').val(),
				'url': location.protocol + '//' + location.hostname
		}

		data.success = function(data){
			$('.loginlink_input', $input).val(data.result.link)
		}
		
		get_ajax('user/loginlink', data);

	})

}

function loginlink_show_error(error){
	
	$('.loginlink_error_active').removeClass('loginlink_error_active')
	$('.loginlink_error_' + error).addClass('loginlink_error_active')
	
}

function loginlink_resize(){
	
}

function loginlink_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		loginlink_resize();
	});
	
	$(window).on('scroll.cms', function(){
		loginlink_scroll();
	});
	
	loginlink_init();

	loginlink_resize();
	
	loginlink_scroll();

});
