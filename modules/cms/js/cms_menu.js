
function cms_menu_open($this){
	$('.cms_menu_children', $this).addClass('cms_menu_children_hover');
}

function cms_menu_init($root){

	var $scope = $root ? $root.find('.cms_menu_container') : $('.cms_menu_container');

	$scope.not('.cms_menu_ok').each(function(){

		var $container = $(this);

		$container.addClass('cms_menu_ok');

		// js for backwards compatibility in safari
		$('.cms_menu_parent', $container).hover(
			function(){
				cms_menu_open($(this))
			},
			function(){
				$('.cms_menu_children', this).removeClass('cms_menu_children_hover');
			}
		);

		$('.cms_menu_parent', $container).on('click.cms', function(){
			$('.cms_menu_children_hover').removeClass('cms_menu_children_hover');
			cms_menu_open($(this))
		})

	});

}

$(document).ready(function() {

	cms_menu_init();

});