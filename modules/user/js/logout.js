var base_url = config_url

function logout_init(){
	
	$('.user_logout_container').on('click.cms', function(){
		
		var data = {
				'do': 'logout'
		}
		
		data.success = function(result){

			// redirect to success url
			window.location.href = base_url + $('.user_logout_container').data('link')

		}

		get_ajax('user/logout', data);

	})

}

function logout_resize(){
	
}

function logout_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', logout_resize)
	$(window).on('scroll.cms', logout_scroll)
	
	logout_init()
	logout_resize()
	logout_scroll()

})
