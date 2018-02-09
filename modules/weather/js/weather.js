function weather_init(){
	
	var local_timestamp = (+new Date) / 1000;
	var start_time = parseInt($('.weather_time').data('time'));
	weather_delta = start_time - local_timestamp;

	weather_time();
	setInterval(weather_time, 1000);
	
	setInterval(function(){
		get_ajax('jmeel/weather', {
			'do':'get_weather',
			'success':function(data){
				$('.weather_weather').html(data.weather);
			}
		});
	}, 300000);
	
}

function weather_time(){
	
	var time_to_show = weather_delta + (+new Date) / 1000;

	var t = new Date(time_to_show * 1000);
	var formatted = ('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2);
	
	$('.weather_time').html(formatted);
	
}

function weather_resize(){
	
}

function weather_scroll(){
	
}

$(document).ready(function() {

	$(window).on('resize.cms', function(){
		weather_resize();
	});
	
	$(window).on('scroll.cms', function(){
		weather_scroll();
	});
	
	weather_init();

	weather_resize();
	
	weather_scroll();

});
