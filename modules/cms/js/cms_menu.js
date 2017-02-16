
function cms_menu_init(){
	
	// js for backwards compatibility in safari
	$('.cms_menu_parent').hover(
		function(){
			$('.cms_menu_children', this).addClass('cms_menu_children_hover');
		},
		function(){
			$('.cms_menu_children', this).removeClass('cms_menu_children_hover');
		}
	);
}

$(document).ready(function() {
	
	cms_menu_init();
	
});
