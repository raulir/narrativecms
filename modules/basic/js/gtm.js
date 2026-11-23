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

function gtm_init(){
	
	var $gtm_container = $('.gtm_container');
	
	if ($gtm_container.length && basic_tracking_allowed($gtm_container)){
		
		setTimeout(() => {
	
			(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
				new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
				j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
				'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
				})(window,document,'script','dataLayer', $gtm_container.data('gtm_id'));
	
		}, $gtm_container.data('delay'))
	
	}

}


$(document).ready(function() {

	gtm_init();

});
