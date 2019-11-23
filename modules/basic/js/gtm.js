function gtm_init(){
	
	var $gtm_container = $('.gtm_container');
	
	if ($gtm_container.length){
		
		setTimeout(() => {
	
			(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
				new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
				j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
				'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
				})(window,document,'script','dataLayer', $gtm_container.data('gtm_id'));
	
		}, $gtm_container.data('delay'))
	
	}

}

function gtm_resize(){

}

function gtm_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		gtm_resize();
	});
	
	$(window).on('scroll.cms', function(){
		gtm_scroll();
	});
	
	gtm_init();

	gtm_resize();
	
	gtm_scroll();

});
