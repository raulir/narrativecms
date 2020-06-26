
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
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', cms_resize);
	$(window).on('scroll.cms', cms_scroll);
	
	cms_init();
	cms_resize();
	cms_scroll();

});
