function admin_form_init(){

	$('.admin_form_data').on('click.crl', function(){

		window.location = window.location.href + $(this).data('id') + '/';
		
	});

}

function admin_form_resize(){

}

$(document).ready(function() {

	$(window).on('resize.r', function(){
		admin_form_resize();
	});

	admin_form_init();
	admin_form_resize();

});
