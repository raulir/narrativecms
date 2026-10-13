function language_close_dropdown($button){

	if ($button && $button.length){
		$button.addClass('language_dropdown_closed')
	}

}

function language_init(){

	$('.language_button').on('mouseenter.cms', function(){
		$(this).removeClass('language_dropdown_closed')
	})

	$('.language_language').on('click.cms', function(){

		var $this = $(this)
		var $button = $this.closest('.language_button')

		language_close_dropdown($button)
		language_clear_cookies()

		get_ajax('basic/language', {
			'do':'language_set',
			'language_id': $this.data('language_id'),
			'success': function(){
				location.reload();
			}
		})

	});

}

function language_clear_cookies(){

	var paths = ['/']
	var base = (typeof _cms_base === 'string') ? _cms_base : ''
	var bits = location.pathname.split('/')
	var acc = ''
	var i

	if (base && paths.indexOf(base) === -1){
		paths.push(base)
	}

	for (i = 0; i < bits.length; i++){
		if (bits[i] === ''){
			continue
		}
		acc += '/' + bits[i]
		if (paths.indexOf(acc) === -1){
			paths.push(acc)
		}
		if (paths.indexOf(acc + '/') === -1){
			paths.push(acc + '/')
		}
	}

	for (i = 0; i < paths.length; i++){
		document.cookie = 'language=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + paths[i] + '; SameSite=Lax'
	}

}

function language_resize(){

}

function language_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		language_resize();
	});
	
	$(window).on('scroll.cms', function(){
		language_scroll();
	});
	
	language_init();

	language_resize();
	
	language_scroll();

});
