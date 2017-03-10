function admin_webform_init(){

	$('.admin_webform_data').on('click.crl', function(){

		window.location = window.location.href + $(this).data('id') + '/';
		
	});

}

function admin_webform_resize(){

}

$(document).ready(function() {

	$(window).on('resize.r', function(){
		admin_webform_resize();
	});

	admin_webform_init();
	admin_webform_resize();

});
