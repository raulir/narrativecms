
function strip_tags(input, allowed) {
  allowed = (((allowed || '') + '')
    .toLowerCase()
    .match(/<[a-z][a-z0-9]*>/g) || [])
    .join(''); // making sure the allowed arg is a string containing only tags in lowercase (<a><b><c>)
  var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi,
    commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi;
  return input.replace(commentsAndPhpTags, '')
    .replace(tags, function($0, $1) {
      return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : '';
    });
}

/*
 * if timer 0, then doesn't hide
 */
function cms_notification(text, timer, type){
	
	if (!type){
		type = 'success';
	}
	
	if ($('.cms_notification_container').length == 0){
		$('body').append('<div class="cms_notification_container cms_notification_' + type + '">' + text + '</div>');
		$('.cms_notification_container').css({'left': ($(window).innerWidth() - $('.cms_notification_container').outerWidth())/2 + 'px'});
		setTimeout(function(){
			$('.cms_notification_container').css({'opacity':'1'});
		}, 100);
	}
	
	if (timer){
		setTimeout(function(){
			$('.cms_notification_container').css({'opacity':'0'});
			setTimeout(function(){
				$('.cms_notification_container').remove();
			}, 600);
		}, timer * 1000);
	}
	
}

function cms_error(text, timer){
	cms_notification(text, timer, 'error');
}

function cms_resize(){
	
	$('body').css({'height': parseInt($(window).innerHeight()) - 0.5 + 'px'});

}

function cms_scroll(){
	
	var scrolltop = self.pageYOffset || document.documentElement.scrollTop  || document.body.scrollTop;
	if (scrolltop > 0){
		$('.cms_header_container').addClass('cms_header_active');
	} else {
		$('.cms_header_container').removeClass('cms_header_active');
	}
	
}

function cms_init(){
	
	// ctrl keybindings
	var $cms_ctrl = $('*[data-cms_ctrl]')
	$cms_ctrl.each(function(){
		
		var $this = $(this)
		
		if ($this.hasClass('cms_ctrl_ok')) return
		
		$this.append('<div class="cms_ctrl_hint">' + $this.data('cms_ctrl') + '</div>')
				.addClass('cms_ctrl_key_' + $this.data('cms_ctrl').toString().toLowerCase())
				.addClass('cms_ctrl_ok')

	})
	
	if ($cms_ctrl.length){
	
		$(window).off('keydown.cms_ctrl').on('keydown.cms_ctrl', function(event) {
		
			if (event.key == 'Control') {
				$('.cms_ctrl_hint').addClass('cms_ctrl_hint_active');
			}

		    if (event.ctrlKey) {
		    	
		    	var event_key = event.key.toLowerCase()

		    	if($('.cms_ctrl_key_' + event_key).length){
		    		event.preventDefault()
		    		if($('body > .cms_popup_container').length){
		    			$('.cms_ctrl_key_' + event_key, $('body > .cms_popup_container')).first()[0].click()
		    		} else {
		    			$('.cms_ctrl_key_' + event_key).first()[0].click()
		    		}
		    	}

		    }

		});
	
		$(window).off('keyup.cms_ctrl').on('keyup.cms_ctrl', function(event) {
	
			if (event.key == 'Control') {
				$('.cms_ctrl_hint_active').removeClass('cms_ctrl_hint_active');
			}
	
		})
	
	}

}

$(document).ready(function() {
	
	$(window).on('resize.cms', cms_resize);
	$(window).on('scroll.cms', cms_scroll);
	
	cms_init();
	cms_resize();
	cms_scroll();

});
