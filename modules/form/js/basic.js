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
			}, 30000)
		}
		
	});
	
	if (missing == 0){
		
		$('.form_basic_input_input', $container).blur();
		
		$('.form_basic_message', $container).addClass('form_basic_message_active');
		setTimeout(function(){
			$('.form_basic_message', $container).addClass('form_basic_message_status_sending');
		}, 50);
		
		$('.form_basic_input_file', $container).remove()
		
		var fa = $form.serializeArray();
		var fo = {};
		$.each(fa,
		    function(i, v) {
		        fo[v.name] = v.value;
		    });
		
		fo['_page'] = document.title
		
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
//				$('.form_basic_close', $container).click();
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

		$('.form_basic_input_file').on('change.cms', function(){
			var $this = $(this)
			$this.off('change.cms')
			form_basic_file_upload($this)
		})
		
	})

}

function form_basic_file_upload($this){
	
	var data = new FormData();
	
	data.append('file', $this.get(0).files[0])
	
	// var data = new FormData( $('.form_basic_image_form').get(0) );
	
	data.append('panel_id', 'form/form_upload');
	data.append('do', 'form_upload');
	
	form_basic_file_transfer(data, function(data){
		
		console.log(data)

		$('.form_basic_input_file_status', $this.closest('.form_basic_input_file_area'))
			.html($this.closest('.form_basic_input_file_area').data('finishing_label') + ' ' + data.result.filename_clean)
			
		$('.form_basic_input_file_url', $this.closest('.form_basic_input_file_area')).val(data.result.filename)
		
		$this.on('change.cms', function(){
			$this.off('change.cms')
			form_basic_file_upload($this)
		})

	}, $('.form_basic_input_file_status', $this.closest('.form_basic_input_file_area')));

}

function form_basic_file_transfer(data, success, $info){
	
	var percentage = 0;
	var label = '0%';
	
	$.ajax( {
		url: config_url + 'ajax_api/get_panel',
	    type: 'POST',
	    data: data,
	    processData: false,
	    contentType: false,
	    dataType: 'json',
	    success: function(data){
	    	
	    	success(data);

	    },
	    xhr: function() {
	        var xhr = new window.XMLHttpRequest();
	
	        xhr.upload.addEventListener('progress', function(evt) {
	        	if (evt.lengthComputable) {
	        	  
	        		var percentComplete = evt.loaded / evt.total;
	        		percentComplete = parseInt(percentComplete * 100);
	        		
	        		/*
        			$('.cms_images_container_bg').css({'width': percentComplete + '%'});
        			*/

        			/*
	        		if (percentComplete < 99){
		        		$('.cms_images_container_label').html(percentComplete + '%');
		        		label = percentComplete + '%';
	        		} else {
		        		$('.cms_images_container_label').html('finishing');
		        		label = 'finishing';
	        		}
	        		*/
        			
        			$info.html(percentComplete + '%')
        			
        			console.log(percentComplete);
	
	        	}
	        }, false);
	
	        return xhr;
	    }
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