function login_google_init($root){

	var $scope = $root ? $root.find('.login_google_container') : $('.login_google_container')

	$scope.not('.login_google_ok').each(function(){

		$(this).addClass('login_google_ok')
		// Credential handling: window.user_google_credential_response (user_google_button.js)

	})

}

function login_google_destroy($root){

	var $scope = $root ? $root.find('.login_google_container') : $('.login_google_container')

	$scope.filter('.login_google_ok').each(function(){

		$(this).removeClass('login_google_ok')

	})

}

$(document).ready(function(){

	login_google_init()

})
