function form_basic_submit($this){
	
	var $container = $this.closest('.form_basic_container')

	var $form = $this.closest('form');
	
	var missing = 0;
	$('.form_basic_mandatory', $form).each(function(){
		
		var $this = $(this)
		
		var value = ''
		var $target = $this.parent()
		
		if ($this.hasClass('form_basic_input_radio')){
			if($('.form_basic_input_radio_input:checked', $this).length){
				value = $('.form_basic_input_radio_input:checked', $this).val()
			}
			$target = $this
		} else {
			value = $this.val()
		}

		if (value == '' || ($this.attr('name') == 'email' && !(value.indexOf('@') >= 0) )){
			missing = missing + 1;
			$target.addClass('form_basic_error');
			setTimeout(function(){
				$target.removeClass('form_basic_error');
			}, 5000)
		}
		
	});
	
	if (missing == 0){
		
		$('.form_basic_input_input', $container).blur();
		
		$('.form_basic_message', $container).addClass('form_basic_message_active');
		setTimeout(function(){
			$('.form_basic_message', $container).addClass('form_basic_message_status_sending');
		}, 50);
		
		var fa = $form.serializeArray();
		var fo = {};
		$.each(fa,
		    function(i, v) {
		        fo[v.name] = v.value;
		    });
		
		get_ajax('form/do_send', $.extend({ 'success': function(data){
			
			// register google analytics event
			if (typeof analytics_trackers !== 'undefined'){
				analytics_send('event', 'Form', 'submit', $("input[name='id']", $form).val(), 10);
			}

			// register gtag event
			if (typeof gtag !== 'undefined'){
			
				gtag('event', 'form', {
			    	'event_category': 'submit',
			    	'event_label': $("input[name='id']", $form).val(),
			    	'transport_type': 'beacon',
			    	'value': 10
				})

			}
			
			setTimeout(function(){
				setTimeout(function(){
					$('.form_basic_message', $container).removeClass('form_basic_message_status_sending').addClass('form_basic_message_status_active');
				}, 50);
				
			}, 500);
		
			setTimeout(function(){
				$('.form_basic_message', $container).removeClass('form_basic_message_status_active').removeClass('form_basic_message_active');
				$('.form_basic_input_input', $form).val('');
				$('.form_basic_close', $container).click();
			}, 300000)
			
			if (typeof form_basic_after !== 'undefined'){
				setTimeout(function(){
					form_basic_after()
				}, 1000)
			}
			
			// do after event
			if ($container.data('success_url')){
				window.location = $container.data('success_url')
			}
			
		}}, fo));
				
		// if needs to send success pageview
		if ($container.data('virtual_success_url') && typeof gtag !== 'undefined'){
			
			gtag('event', 'page_view', {
				'page_title': 'Form success',
				'page_path': $container.data('virtual_success_url'),
				'transport_type': 'beacon'
			})

		}
		
	}

}

function form_basic_init(){
	
	$('.form_basic_container').each(function(){
		
		var $container = $(this);
		
		if ($container.data('init_ok')){
			return
		}
		
		$container.data('init_ok', true)
		
		$('.form_basic_form>form', $container).on('submit.cms', function(){
			$('.form_basic_submit', $(this)).click();
			return false;
		});
		
		$('.form_basic_submit', $container).on('click.cms', function(){
			
			var $this = $(this)
			
			if (!$('#recaptcha').length){
				form_basic_submit($this)
			} else {
				grecaptcha.ready(function() {
					grecaptcha.execute($('#recaptcha').data('key'), {action: 'form'}).then(function(token) {
						$('.form_basic_form>form', $container).append('<input type="hidden" name="recaptcha_token" value="' + token + '">')
						form_basic_submit($this)
					})
			    })
			}
			
		})
		
		$('.form_basic_input_input', $container).each(function(){
			var $this = $(this);
			if (parseInt($this.data('limit'))){
				$this.on('change.cms keyup.cms', function(){
					while( $this.val().length > parseInt($this.data('limit')) ){
						$this.val($this.val().slice(0, - 1));
					}
				});
			}
		});
		
		$('.form_basic_input_checkbox', $container).on('click.cms', function(){
			
			var $target = $('#' + $(this).data('target'))
			
			var val = parseInt($target.val())

			if (val){
				$target.val(0);
				$(this).removeClass('form_basic_input_checkbox_active');
			} else {
				$target.val(1);
				$(this).addClass('form_basic_input_checkbox_active');
			}
			
		});
		
		$('.form_basic_close', $container).on('click.cms', function(){
			
			$container.remove();
			
		});
		
		if ($container.hasClass('form_basic_recaptcha_on') && !$('#recaptcha').length){
			
			$('body').append('<script id="recaptcha" src="https://www.google.com/recaptcha/api.js?render=' + 
					$container.data('recaptcha_key') + '" data-key="' + $container.data('recaptcha_key') + '"></script>')
			
		}
		
	})

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