function webform_init(){
	
	$('.webform_submit').on('click.crl', function(){
		
		var $form = $(this).closest('form');
		
		var missing = 0;
		$('.webform_mandatory', $form).each(function(){
			var $this = $(this);
			if ($this.val() == '' || ($this.attr('name') == 'email' && !($this.val().indexOf('@') >= 0) )){
				missing = missing + 1;
				$this.parent().addClass('webform_error');
				setTimeout(function(){
					$this.parent().removeClass('webform_error');
				}, 5000);
			}
		});
		
		if (missing == 0){
			
			$('.webform_message').addClass('webform_message_active');
			setTimeout(function(){
				$('.webform_message').addClass('webform_message_status_sending');
			}, 50);
			
			var fa = $form.serializeArray();
			var fo = {};
			$.each(fa,
			    function(i, v) {
			        fo[v.name] = v.value;
			    });
			
			get_ajax('form/do_send', $.extend({ 'success': function(data){
				
				// register google analytics event
				if (typeof ga !== 'undefined'){
					ga('send', 'event', 'Form', 'submit', $("input[name='id']", $form).val(), 10);
				}

				setTimeout(function(){
					setTimeout(function(){
						$('.webform_message').removeClass('webform_message_status_sending').addClass('webform_message_status_active');
					}, 50);
					
				}, 500);
    		
				setTimeout(function(){
					$('.webform_message').removeClass('webform_message_status_active').removeClass('webform_message_active');
				}, 30000);
				
			}}, fo));
			
		}

	});
	
	$('.webform_input_input').each(function(){
		var $this = $(this);
		if (parseInt($this.data('limit'))){
			$this.on('change.crl keyup.crl', function(){
				while( $this.val().length > parseInt($this.data('limit')) ){
					$this.val($this.val().slice(0, - 1));
				}
			});
		}
	});
	
}

function webform_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.r', function(){
		webform_resize();
	});

	webform_resize();
	
	webform_init();
	
});