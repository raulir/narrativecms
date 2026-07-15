var user_progress_timer = 0
var user_progress_default_message = 'One moment...'

function user_progress_ensure_dom(){

	var $overlay = $('.user_progress_overlay')
	if ($overlay.length){
		return $overlay
	}

	$('body').append(
		'<div class="user_progress_overlay">' +
			'<div class="user_progress_veil"></div>' +
			'<div class="user_progress_message"></div>' +
		'</div>'
	)

	return $('.user_progress_overlay')

}

/**
 * Full-page lighten immediately; show message after 1s (units prepare pattern).
 * @param {string} [message]
 */
function user_progress_show(message){

	var text = message || user_progress_default_message
	if (!text){
		text = 'One moment...'
	}

	var $overlay = user_progress_ensure_dom()
	$overlay.find('.user_progress_message').text(text)
	$overlay.removeClass('user_progress_message_visible')
	$overlay.addClass('user_progress_active')

	clearTimeout(user_progress_timer)
	user_progress_timer = setTimeout(function(){
		$overlay.addClass('user_progress_message_visible')
	}, 1000)

}

function user_progress_hide(){

	clearTimeout(user_progress_timer)
	user_progress_timer = 0

	$('.user_progress_overlay').removeClass('user_progress_active user_progress_message_visible')

}

/**
 * Prefer data-progress_message on a container (from user settings).
 */
function user_progress_message_from($el){

	if ($el && $el.length){
		var msg = $el.data('progress_message')
		if (msg){
			return msg
		}
	}

	var $any = $('[data-progress_message]').first()
	if ($any.length && $any.data('progress_message')){
		return $any.data('progress_message')
	}

	return user_progress_default_message

}
