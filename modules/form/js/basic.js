function form_basic_init(){

	$('.form_basic_form>form').on('submit.cms', function(){
		$('.form_basic_submit', $(this)).click();
		return false;
	});
	
	$('.form_basic_submit').on('click.crl', function(){

		var $form = $(this).closest('form');
		
		var missing = 0;
		$('.form_basic_mandatory', $form).each(function(){
			var $this = $(this);
			if ($this.val() == '' || ($this.attr('name') == 'email' && !($this.val().indexOf('@') >= 0) )){
				missing = missing + 1;
				$this.parent().addClass('form_basic_error');
				setTimeout(function(){
					$this.parent().removeClass('form_basic_error');
				}, 5000);
			}
		});
		
		if (missing == 0){
			
			$('.form_basic_input_input').blur();
			
			$('.form_basic_message').addClass('form_basic_message_active');
			setTimeout(function(){
				$('.form_basic_message').addClass('form_basic_message_status_sending');
			}, 50);
			
			var fa = $form.serializeArray();
			var fo = {};
			$.each(fa,
			    function(i, v) {
			        fo[v.name] = v.value;
			    });
			
			get_ajax('form/do_send', $.extend({ 'success': function(data){
				
				// register google analytics event
				if (typeof ga_cms !== 'undefined'){
					ga_cms('send', 'event', 'Form', 'submit', $("input[name='id']", $form).val(), 10);
				}

				setTimeout(function(){
					setTimeout(function(){
						$('.form_basic_message').removeClass('form_basic_message_status_sending').addClass('form_basic_message_status_active');
					}, 50);
					
				}, 500);
    		
				setTimeout(function(){
					$('.form_basic_message').removeClass('form_basic_message_status_active').removeClass('form_basic_message_active');
					$('.form_basic_input_input', $form).val('');
					$('.form_basic_close', $form.closest('.form_basic_container')).click();
				}, 300000);
				
			}}, fo));
			
			// do after event
			if ($form.closest('.form_basic_container').data('success_url')){
				window.location = $form.closest('.form_basic_container').data('success_url');
			}
			
		}

	});
	
	$('.form_basic_input_input').each(function(){
		var $this = $(this);
		if (parseInt($this.data('limit'))){
			$this.on('change.crl keyup.crl', function(){
				while( $this.val().length > parseInt($this.data('limit')) ){
					$this.val($this.val().slice(0, - 1));
				}
			});
		}
	});
	
	$('.form_basic_input_checkbox').on('click.cms', function(){
		
		var $target = $('#' + $(this).data('target'));
		
		if ($target.val()){
			$target.val(0);
			$(this).removeClass('form_basic_input_checkbox_active');
		} else {
			$target.val(1);
			$(this).addClass('form_basic_input_checkbox_active');
		}
		
	});
	
	$('.form_basic_close').on('click.cms', function(){
		
		$(this).closest('.form_basic_container').remove();
		
	});
	
}

function form_basic_resize(){

}

$(document).ready(function() {
	
	$(window).on('resize.r', function(){
		form_basic_resize();
	});

	form_basic_resize();
	
	form_basic_init();
	
});