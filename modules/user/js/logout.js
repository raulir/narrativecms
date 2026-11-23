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
		window.location.href = window.location.origin + ((typeof cms_app_root === 'function') ? cms_app_root() : '/')
	}

	var job = (typeof music_offline_drop_account === 'function')
		? music_offline_drop_account(email)
		: Promise.resolve()

	job.then(finish, finish)

}

function logout_init(){
	
	$('.user_logout_container').on('click.cms', function(){
		
		var data = {
				'cms_do': 'logout'
		}
		
		data.success = function(){

			if (cms_platform() !== 'site'){
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


$(document).ready(function() {

	logout_init()

});