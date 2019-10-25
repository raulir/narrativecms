window.dataLayer = window.dataLayer || []

function gtag(){
	dataLayer.push(arguments)
}

function gtag_init(){
	
	gtag('js', new Date());
	
	var $gtag_container = $('.basic_gtag_container');
	
	if ($gtag_container.length){
		
		var ids = $gtag_container.data('ids').split(',')
		
		if (ids.length == 0) return;
		
		ids.forEach(id => gtag('config', id))
		
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
