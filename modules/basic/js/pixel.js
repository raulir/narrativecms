function pixel_init(){
	
	var $pixel_container = $('.pixel_container');
	
	if ($pixel_container.length){
	
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

function pixel_resize(){

}

function pixel_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		pixel_resize();
	});
	
	$(window).on('scroll.cms', function(){
		pixel_scroll();
	});
	
	pixel_init();

	pixel_resize();
	
	pixel_scroll();

});
