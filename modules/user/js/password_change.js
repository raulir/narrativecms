function password_change_init($root){

	var $scope = $root ? $root.find('.password_change_container') : $('.password_change_container')

	$scope.not('.password_change_ok').each(function(){

		var $container = $(this)
		$container.addClass('password_change_ok')

		$container.find('.password_change_save').on('click.cms', function(){

			var data = {
				'do': 'save',
				'password': $container.find('.password_change_input_password').val(),
				'password2': $container.find('.password_change_input_password2').val()
			}

			data.success = function(result){

				if (result.result && result.result.error){
					password_change_show_error($container, result.result.error)
					return
				}

				password_change_show_success($container)

			}

			$container.find('.password_change_error_active').removeClass('password_change_error_active')

			get_ajax('user/password_change', data)

		})

	})

}

function password_change_show_error($container, error){

	$container.find('.password_change_error_active').removeClass('password_change_error_active')
	$container.find('.password_change_error_' + error).addClass('password_change_error_active')

}

function password_change_show_success($container){

	$container.find('.password_change_error_active').removeClass('password_change_error_active')
	$container.find('.password_change_input_password, .password_change_input_password2').val('')
	$container.addClass('password_change_overlay_active')

}

function password_change_destroy($root){

	var $scope = $root ? $root.find('.password_change_container') : $('.password_change_container')

	$scope.filter('.password_change_ok').each(function(){

		var $el = $(this)
		$el.removeClass('password_change_ok password_change_overlay_active')
		$el.off('.cms')
		$el.find('.password_change_save').off('.cms')

	})

}

$(document).ready(function(){

	password_change_init()

})
