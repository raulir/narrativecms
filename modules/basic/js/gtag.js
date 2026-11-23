window.dataLayer = window.dataLayer || []

/**
 * Tracking only with consent where the gdpr module asks for it (data-consent="1"): the visitor closed its popup
 * (cookie gdpr) and did not choose "no tracking" (gdpr_notrack). Same in gtag.js, gtm.js and pixel.js.
 */
window.basic_tracking_allowed = window.basic_tracking_allowed || function($container){
	if (String($container.attr('data-consent')) !== '1'){
		return true
	}
	var read = typeof cms_cookie_read === 'function' ? cms_cookie_read : function(){ return null }
	return !!read('gdpr') && !read('gdpr_notrack')
}

function injectScript(src, id) {

	return new Promise((resolve, reject) => {

		var s = 'script'
		var js, fjs = document.getElementsByTagName(s)[0]

		if (!id) {
			id = src
		}

		if (document.getElementById(id)) {
			reject('Script loaded already: ' + src)
			return
		}

		js = document.createElement(s)
		js.id = id
		js.addEventListener('load', resolve)
		js.addEventListener('error', () => reject('Script loading error: ' + src))
		js.src = src
		fjs.parentNode.insertBefore(js, fjs)

	})

}

function gtag(){
	dataLayer.push(arguments)
}

function gtag_init(){
	
	gtag('js', new Date());
	
	var $gtag_container = $('.basic_gtag_container');
	if ($gtag_container.length && basic_tracking_allowed($gtag_container)){
	
		var $items = $('.basic_gtag_item');

		if ($items.length == 0) return;
		
		var ids = [];
		
		$items.each(function(){
			var $this = $(this)
			ids.push($this.data('id'))
			if($this.data('domains')){
				gtag('config', $this.data('id'), {'linker': {'domains': $this.data('domains').split(',') }})
			} else {
				gtag('config', $this.data('id'))
			}
		})

		setTimeout(function(){
			
			injectScript('https://www.googletagmanager.com/gtag/js?id=' + ids[0])
			.then(

				() => {
				
					$('a[target=_blank]').on('click.cms',
						function(){
							gtag('event', 'click', {
							    'event_category': 'outbound',
							    'event_label': $(this).attr('href'),
							    'transport_type': 'beacon'
							})
							return true
						}
					)
				
					$('a[href^="mailto:"]').on('click.cms',
						function(){
							gtag('event', 'click', {
							    'event_category': 'mailto',
							    'event_label': $(this).attr('href'),
							    'transport_type': 'beacon'
							})
							return true
						}
					)
					
					$('a[href^="tel:"]').on('click.cms',
						function(){
							gtag('event', 'click', {
							    'event_category': 'tel',
							    'event_label': $(this).attr('href'),
							    'transport_type': 'beacon'
							})
							return true
						}
					)
				}

			)

		}, $gtag_container.data('delay'))

	}

}

$(document).ready(function() {

	gtag_init();

});
