function test_init(){

}

function test_resize(){
	
	$('.emailer_test_send').on('click.cms', function(){
		
		var parameters = {
			'from_address': $('#from').val(),
			'from_name': $('#from_name').val(),
			'to_addresses': $('#test_to_addresses').val(),
			'subject': $('#subject').val(),
			'body': $('textarea[name="panel_params[body]"]').val(),
			'do': 'send',
		}
		
		console.log(parameters)
		
		get_ajax_panel('emailer/send', parameters, function(result){
								
			$('.emailer_test_result').html(result.result._html)

		})
		
		
	})

}

function test_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', test_resize)
	
	$(window).on('scroll.cms', test_scroll)
	
	test_init()
	test_resize()
	test_scroll()

})
