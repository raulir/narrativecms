function admin_form_init(){

	$('.admin_form_data').on('click.cms', function(){

		window.location = window.location.href + $(this).data('id') + '/';
		
	});

}


$(document).ready(function() {

	admin_form_init();

});
