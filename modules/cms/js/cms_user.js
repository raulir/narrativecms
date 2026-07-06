function cms_user_init($root){

	var $scope = $root ? $root.find('.cms_user_container') : $('.cms_user_container');

	$scope.not('.cms_user_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_user_ok');

		$('.cms_user_button', $container).on('click.cms', function(){

			$('.cms_user_form').get(0).submit();

		});

	});

}

function cms_user_resize(){

}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		cms_user_resize();
	});

	cms_user_init();

	cms_user_resize();

});