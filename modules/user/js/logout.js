function logout_return_to_app(){

	var email = ''

	try {
		var saved = JSON.parse(localStorage.getItem('app_session') || '')
		if (saved && saved.email){
			email = saved.email
		}
	} catch (e) {}

	var finish = function(){
		localStorage.setItem('app_session', JSON.stringify({
			email: email,
			session_id: ''
		}))
		window.location.href = window.location.origin + '/'
	}

	var job = (typeof music_offline_drop_account === 'function')
		? music_offline_drop_account(email)
		: Promise.resolve()

	job.then(finish, finish)

}

function logout_init(){
	
	$('.user_logout_container').on('click.cms', function(){
		
		var data = {
				'do': 'logout'
		}
		
		data.success = function(){

			if (window._cms_inapp){
				logout_return_to_app()
				return
			}

			if ($('.user_logout_container').data('success')){
				window.location.href = $('.user_logout_container').data('success')
			}

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

});