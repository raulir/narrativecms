/**
 * Shared Google button: credential callback so we can show progress overlay
 * (GSI iframe cannot bubble click to the parent). Then form-POST to auth page.
 */
function user_google_credential_response(response){

	if (typeof user_progress_show === 'function'){
		user_progress_show(user_progress_message_from($('.login_google_container, .register_google_container').first()))
	}

	var $host = $('.login_google_container, .register_google_container').first()
	var login_uri = $host.data('login_uri') || ''

	if (!login_uri || !response || !response.credential){
		if (typeof user_progress_hide === 'function'){
			user_progress_hide()
		}
		return
	}

	var form = document.createElement('form')
	form.method = 'POST'
	form.action = login_uri
	form.style.display = 'none'

	var input = document.createElement('input')
	input.type = 'hidden'
	input.name = 'credential'
	input.value = response.credential
	form.appendChild(input)

	// auth_google signs in only on this action with the session's state (user_google_model::web_auth_state)
	var fields = { cms_do: 'google', state: String($host.attr('data-state') || '') }
	Object.keys(fields).forEach(function(name){
		var field = document.createElement('input')
		field.type = 'hidden'
		field.name = name
		field.value = fields[name]
		form.appendChild(field)
	})

	document.body.appendChild(form)
	form.submit()

}

// Global name for GSI data-callback attribute
window.user_google_credential_response = user_google_credential_response
