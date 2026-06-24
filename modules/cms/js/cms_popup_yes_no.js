function cms_popup_yes_no_init($popup){

	if ($popup && $popup.length){
		$popup.css({'opacity':'1'});
	}

}

function cms_popup_yes_no_resize(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		cms_popup_yes_no_resize();
	});

	cms_popup_yes_no_resize();

});