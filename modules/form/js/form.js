function form_init(){

	$('.form_form>form').on('submit.cms', function(){
		$('.form_submit').click();
		return false;
	});
	
	$('.form_submit').on('click.crl', function(){

		var $form = $(this).closest('form');
		
		var missing = 0;
		$('.form_mandatory', $form).each(function(){
			var $this = $(this);
			if ($this.val() == '' || ($this.attr('name') == 'email' && !($this.val().indexOf('@') >= 0) )){
				missing = missing + 1;
				$this.parent().addClass('form_error');
				setTimeout(function(){
					$this.parent().removeClass('form_error');
				}, 5000);
			}
		});
		
		if (missing == 0){
			
			$('.form_input_input').blur();
			
			$('.form_message').addClass('form_message_active');
			setTimeout(function(){
				$('.form_message').addClass('form_message_status_sending');
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
						$('.form_message').removeClass('form_message_status_sending').addClass('form_message_status_active');
					}, 50);
					
				}, 500);
    		
				setTimeout(function(){
					$('.form_message').removeClass('form_message_status_active').removeClass('form_message_active');
					$('.form_input_input', $form).val('');
				}, 10000);
				
			}}, fo));
			
		}

	});
	
	$('.form_input_input').each(function(){
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

function form_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.r', function(){
		form_resize();
	});

	form_resize();
	
	form_init();
	
});