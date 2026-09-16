function subscribe_email_ok(value){

	var email = String(value || '').trim()
	return email !== '' && email.indexOf('@') >= 0

}

function subscribe_submit($el){

	var $email = $el.find('.subscribe_email').first()
	var email = String($email.val() || '').trim()
	var $error = $el.find('.subscribe_error').first()
	var $message = $el.find('.subscribe_message').first()

	if (!subscribe_email_ok(email)){
		$el.addClass('subscribe_invalid')
		$error.addClass('subscribe_error_show')
		window.setTimeout(function(){
			$el.removeClass('subscribe_invalid')
			$error.removeClass('subscribe_error_show')
		}, 8000)
		return
	}

	if ($el.hasClass('subscribe_busy')){
		return
	}
	$el.addClass('subscribe_busy')
	$message.addClass('subscribe_message_active subscribe_message_sending_on')

	var id = parseInt($el.attr('data-cms_page_panel_id'), 10)
	if (isNaN(id)){
		id = 0
	}

	get_ajax_panel('emailer/subscribe', {
		'do': 'send_form',
		'no_html': '1',
		'cms_page_panel_id': id,
		'id': id,
		'email': email,
		'_page': document.title,
		'_ajax_error': function(){
			$el.removeClass('subscribe_busy')
			$message.removeClass('subscribe_message_active subscribe_message_sending_on')
			$error.text('Could not send. Try again.').addClass('subscribe_error_show')
		}
	}, function(data){

		var result = (data && data.result) ? data.result : {}
		if (result.error || result.message !== 'ok'){
			$el.removeClass('subscribe_busy')
			$message.removeClass('subscribe_message_active subscribe_message_sending_on')
			$error.text(result.error || 'Could not send. Try again.').addClass('subscribe_error_show')
			window.setTimeout(function(){
				$error.removeClass('subscribe_error_show')
			}, 8000)
			return
		}

		window.setTimeout(function(){
			$message.removeClass('subscribe_message_sending_on').addClass('subscribe_message_ok')
		}, 400)

		if (typeof analytics_trackers !== 'undefined'){
			analytics_send('event', 'Form', 'subscribe', String(id), 10)
		}
		if (typeof gtag !== 'undefined'){
			gtag('event', 'form', {
				'event_category': 'subscribe',
				'event_label': String(id),
				'transport_type': 'beacon',
				'value': 10
			})
		}

		window.setTimeout(function(){
			$email.val('')
			$el.removeClass('subscribe_busy')
			$message.removeClass('subscribe_message_active subscribe_message_ok subscribe_message_sending_on')
		}, 8000)

	})

}

function subscribe_init($root){

	var $scope = $root ? $root.find('.subscribe_container') : $('.subscribe_container')

	$scope.not('.subscribe_ok').each(function(){

		var $el = $(this)
		$el.addClass('subscribe_ok')

		$el.find('.subscribe_submit').on('click.cms', function(){
			subscribe_submit($el)
		})

		$el.find('.subscribe_email').on('keydown.cms', function(e){
			if (e.key === 'Enter' || e.keyCode === 13){
				e.preventDefault()
				subscribe_submit($el)
			}
		})

	})

}

$(function(){
	subscribe_init()
})
