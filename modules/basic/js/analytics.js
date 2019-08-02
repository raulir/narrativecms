var analytics_trackers = []

function analytics_send(type, category, action, label, value){
	
	var params = {
		hitType: type,
	}
	
	if (category){
		params.eventCategory = category 
	}
	
	if (action){
		params.eventAction = action 
	}
	
	if (label){
		params.eventLabel = label 
	}
	
	if (value){
		params.eventValue = value 
	}
console.log(analytics_trackers);	
	$.each(analytics_trackers, (index, value) => ga(value + '.send', params))

}

function analytics_init(){
	
	var $analytics_ids = $('.analytics_id')
	
	if ($analytics_ids.length){
		
		setTimeout(function(){
			
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		  		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		  		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		  		})(window,document,'script','https://www.google-analytics.com/analytics.js','ga')
			
			var i = 0
			
			$analytics_ids.each(function(){
			
				var $this = $(this)

		  		ga('create', $this.data('analytics_id'), 'auto', 'ga_' + i)
		  		analytics_trackers.push('ga_' + i)
		  		
		  		i++
				
			})
			
	  		analytics_send('pageview')

		}, $analytics_ids.data('delay'))

	}

}

function analytics_resize(){

}

function analytics_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', analytics_resize)
	$(window).on('scroll.cms', analytics_scroll)
	
	analytics_init()
	analytics_resize()
	analytics_scroll()

});
