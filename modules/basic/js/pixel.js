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

function pixel_init(){
	
	var $pixel_container = $('.pixel_container');
	
	if ($pixel_container.length && basic_tracking_allowed($pixel_container)){
	
		setTimeout(function(){
			
			!function(f,b,e,v,n,t,s)
	  		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	  		n.callMethod.apply(n,arguments):n.queue.push(arguments)};
	  		if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
	  		n.queue=[];t=b.createElement(e);t.async=!0;
	  		t.src=v;s=b.getElementsByTagName(e)[0];
	  		s.parentNode.insertBefore(t,s)}(window, document,'script',
	  		'https://connect.facebook.net/en_US/fbevents.js');
	  		
	  		fbq('init', $pixel_container.data('pixel_id'));
	  		fbq('track', 'PageView');
			
		}, $pixel_container.data('delay'));

	}

}


$(document).ready(function() {

	pixel_init();

});
