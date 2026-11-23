function cms_language_select_init($root){

	var $scope = $root ? $root.find('.cms_language_select_container') : $('.cms_language_select_container');

	$scope.not('.cms_language_select_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_language_select_ok');

		$('.cms_language_select_option', $container).on('click.cms', function(){

			var $this = $(this);

			get_ajax('cms/cms_language_select', {
				'cms_do': 'cms_language_set',
				'language': $this.data('language'),
				'success': function(){
					location.reload()
				}
			})

		});

	});

}


$(document).ready(function() {

	cms_language_select_init();

});