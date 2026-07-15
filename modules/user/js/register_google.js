function register_google_init($root){

	var $scope = $root ? $root.find('.register_google_container') : $('.register_google_container')

	$scope.not('.register_google_ok').each(function(){

		$(this).addClass('register_google_ok')
		// Credential handling: window.user_google_credential_response (user_google_button.js)

	})

}

function register_google_destroy($root){

	var $scope = $root ? $root.find('.register_google_container') : $('.register_google_container')

	$scope.filter('.register_google_ok').each(function(){

		$(this).removeClass('register_google_ok')

	})

}

$(document).ready(function(){

	register_google_init()

})
