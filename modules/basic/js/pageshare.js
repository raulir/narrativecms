function pageshare_init(){
	
    window.fbAsyncInit = function() {
    	FB.init({
    		appId      : '1605902019498993',
    		xfbml      : false,
    		version    : 'v2.5'
    	});
    };
    
    $('body').append('<div id="fb-root"></div><script src="//connect.facebook.net/en_US/sdk.js"></script>')

	$('.basic_pageshare_icon').off('click.cms').on('click.cms', function(e){
		
		var $this = $(this);
		
		var url = location.href;
		
		if ($this.data('url')){
			var url = location.protocol + '//' + location.host + $button.data('url');
		}
		
		e.stopPropagation();
		var type = $this.data('type');
		
		if (type == 'email'){
			
			window.location.href = 'mailto:?subject=' + encodeURIComponent(document.title) + 
					'&body=' + encodeURIComponent($this.data('content')) + '%0D%0A%0D%0A' + url;
		
		}
		
		if (type == 'facebook'){
			
			FB.ui({
				method: 'share',
				href: url,
			}, function(response){});
			
		}
		
		if (type == 'twitter'){
			
			window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent($this.data('content')) + 
					'%20&url=' + encodeURIComponent(url) + '&hashtags=' + encodeURIComponent($this.data('hashtags')));

		}
		
		if (type == 'linkedin'){
			
			window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url) + 
					'&title=' + encodeURIComponent(document.title));
			
		}

	})

}

function pageshare_resize(){

}

function pageshare_scroll(){

}

$(document).ready(function() {
	
	$(window).on('scroll.cms', function(){
		pageshare_scroll();
	});

	$(window).on('resize.cms', function(){
		pageshare_resize();
	});
	
	pageshare_scroll();
	
	pageshare_resize();
	
	pageshare_init();
	
});
