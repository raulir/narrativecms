function analytics_init(){
	
	var $analytics_container = $('.analytics_container');
	
	if ($analytics_container.length){
	
		setTimeout(function(){
			
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		  		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		  		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		  		})(window,document,'script','https://www.google-analytics.com/analytics.js','ga_cms');
		
		  		ga_cms('create', $analytics_container.data('analytics_id'), 'auto');
		  		ga_cms('send', 'pageview');
			
		}, $analytics_container.data('delay'));

	}

}

function analytics_resize(){

}

function analytics_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		analytics_resize();
	});
	
	$(window).on('scroll.cms', function(){
		analytics_scroll();
	});
	
	analytics_init();

	analytics_resize();
	
	analytics_scroll();

});
