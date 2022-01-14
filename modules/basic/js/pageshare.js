function pageshare_init(){
	
    window.fbAsyncInit = function() {
    	FB.init({
    		appId      : $('.basic_pageshare_container').data('fb_app_id'),
    		xfbml      : false,
    		version    : 'v2.5'
    	});
    };
    
    $('body').append('<div id="fb-root"></div><script src="//connect.facebook.net/en_US/sdk.js"></script>')

	$('.basic_pageshare_icon').off('click.cms').on('click.cms', function(e){
		
		var $this = $(this);
		
		var url = location.href;
		
		if ($this.data('url')){
			var url = location.protocol + '//' + location.host + $this.data('url');
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
			
			var short_url = $this.data('url') !== false ? $this.data('url') : url
			
			if (typeof $this.data('url_key') !== 'undefined'){
				
				get_ajax('basic/pageshare', {
					'do':'shorten', 
					'url_key':$this.data('url_key'), 
					'url': short_url,
					'long_url': url,
					'title': document.title
				}).then((data) => {
					
					window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent($this.data('content')) + 
							'%0A&url=' + encodeURIComponent(data.link) + '&hashtags=' + encodeURIComponent($this.data('hashtags')))
					
				})
				
			} else {
			
				window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent($this.data('content')) + 
						'%0A&url=' + encodeURIComponent(url) + '&hashtags=' + encodeURIComponent($this.data('hashtags')))
			
			}

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
