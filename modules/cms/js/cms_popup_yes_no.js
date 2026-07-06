function cms_popup_yes_no_init($root){

	var $popups = $root ? ($root.hasClass('cms_popup_container') ? $root : $root.find('.cms_popup_container')) : $('.cms_popup_container');

	$popups.not('.cms_popup_yes_no_ok').each(function(){

		var $popup = $(this);

		$popup.addClass('cms_popup_yes_no_ok');
		$popup.css({'opacity':'1'});

	});

}

function cms_popup_yes_no_resize(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_popup_yes_no_resize();
	});

	cms_popup_yes_no_resize();

});