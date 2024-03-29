var cms_fullscreen_open = function(){

	if (document.fullscreenElement !== null){
		console.log('fullcsreen active')
		return
	}

	if (!(document.fullscreenEnabled || document.webkitFullscreenEnabled)){
		console.log('browser doesn\'t allow fullscreen')
		return
	}
		
	if (typeof cms_scroll_lock != 'undefined'){
		cms_scroll_lock()
	}

	var doc = document.documentElement
	if (doc.requestFullscreen) {
	    doc.requestFullscreen()
	} else if (doc.webkitRequestFullscreen) {
		console.log('webkit prefix');
	    docElm.webkitRequestFullscreen();
	}

	return false;

}

var cms_fullscreen_close = function(){
	
	if (typeof cms_scroll_unlock != 'undefined'){
		cms_scroll_unlock()
	}

	if (document.exitFullscreen) {
		document.exitFullscreen()
	} else {
		document.webkitExitFullscreen()
	}
	
}

function basic_vimeo_init(){

	$(document).on('fullscreenchange', function(){
		if (document.fullscreenElement === null){
			$('.basic_vimeo_fullscreen').removeClass('basic_vimeo_fullscreen')
			if (typeof cms_scroll_unlock != 'undefined'){
				cms_scroll_unlock()
			}
		}
	})

	setTimeout(function(){

		var $vimeos = $('.basic_vimeo_container')
		
		$vimeos.each(function(){
		
			var $vimeo = $(this)
			
			if ($vimeo.hasClass('basic_vimeo_ok')){
				return
			}
			$vimeo.addClass('basic_vimeo_ok')
	
			var iframe = $('.basic_vimeo_iframe', $vimeo)[0]
		    var player = new Vimeo.Player(iframe)
			
			$vimeo.data('player', player)
			$vimeo.data('play', 0)
			$vimeo.data('volume', 0.8)
			$vimeo.data('sound', 0)
		    
		    player.setAutopause(true)
		    player.pause()
		    player.setCurrentTime(0)
	
		    if ($vimeo.data('autoplay')){
		    	setTimeout(function(){
		    		basic_vimeo_play($vimeo)
		    	}, 200)
		    } else {
		    	$vimeo.data('sound', 1)
		    	$vimeo.addClass('basic_vimeo_sound_is_on').addClass('basic_vimeo_play_is_off')
		    }
		    
		    $('.basic_vimeo_play', $vimeo).on('click.cms', function(){
		    	$vimeo.data('player').getPaused().then(paused => {
		    		if (paused){
		    			basic_vimeo_stopall().then(() => {
			    			basic_vimeo_play($vimeo)
							if (!$vimeo.data('sound')){
								basic_vimeo_toggle_sound($vimeo)
							}
		    			})
		    		} else {
		    			basic_vimeo_pause($vimeo)
		    		}
		    	})
		    })
		    
		    $('.basic_vimeo_start', $vimeo).on('click', function(){
		    	basic_vimeo_stopall().then(() => {
			    	basic_vimeo_play($vimeo)
					if (!$vimeo.data('sound')){
						basic_vimeo_toggle_sound($vimeo)
					}
					$('.basic_vimeo_start', $vimeo).remove()
		    	})
		    })
		    
		    $('.basic_vimeo_volume_button', $vimeo).on('click.cms', function(){
		    	$vimeo.data('volume', $(this).data('volume'))
		    	if (!$vimeo.data('sound')){
					basic_vimeo_toggle_sound($vimeo)
				}
		    	if ($vimeo.data('sound')){
		    		$vimeo.data('player').setVolume($vimeo.data('volume'))
		    	}
		    	$('.basic_vimeo_volume_button_active', $vimeo).removeClass('basic_vimeo_volume_button_active')
		    	$(this).addClass('basic_vimeo_volume_button_active').prevAll().addClass('basic_vimeo_volume_button_active')
		    	
		    })
			
			$('.basic_vimeo_sound', $vimeo).on('click.cms', function(){
				basic_vimeo_toggle_sound($vimeo)
			})
						
			$('.basic_vimeo_progress_search', $vimeo).on('click.cms', function(e){
			
				$vimeo.data('player').getDuration().then(duration => { 
					var seconds = (e.offsetX / $(this).width()) * duration
					if ($vimeo.data('subs')){
						var found = false
						$vimeo.data('subs').forEach((element, index) => {
							if (!found && element.start_time <= seconds ){
								$vimeo.data('next_subs', index)
							} else {
								found = true
							}
						})
					}
					$vimeo.data('player').setCurrentTime(seconds)
				})
	
			})
			
			$('.basic_vimeo_subtitles_button', $vimeo).on('click.cms', function(){
				if ($vimeo.hasClass('basic_vimeo_subtitles_off')){
					$vimeo.removeClass('basic_vimeo_subtitles_off')
				} else {
					$vimeo.addClass('basic_vimeo_subtitles_off')
				}
			})
			
			$('.basic_vimeo_fullscreen_button', $vimeo).on('click.cms', function(){

				if ($vimeo.hasClass('basic_vimeo_fullscreen')){
					cms_fullscreen_close()
					$vimeo.removeClass('basic_vimeo_fullscreen')
				} else {
					cms_fullscreen_open()
					$vimeo.addClass('basic_vimeo_fullscreen')
				}
			})

			basic_vimeo_player_events($vimeo)

		})
	
	}, 1000)

}

function basic_vimeo_player_events($vimeo){

// console.log($vimeo.data('player'))

	$vimeo.data('player').on('seeked', e => {
		$('.basic_vimeo_progress_current', $vimeo).css({'width': e.percent * 100 + '%'})
	})

	$vimeo.data('player').on('timeupdate', e => {

		var dur = Math.round(e.duration)
		var dur_min = Math.floor(dur/60)
		var dur_sec = dur - dur_min * 60
//			var dur_h = Math.floor(dur_min/60)
//			dur_min = dur_min - dur_h * 60
		
		var cur = Math.round(e.seconds)
		var cur_min = Math.floor(cur/60)
		var cur_sec = cur - cur_min * 60
//			var cur_h = Math.floor(cur_min/60)
//			cur_min = cur_min - cur_h * 60
	
		$('.basic_vimeo_progress_current', $vimeo).css({'width': e.percent * 100 + '%'})
		
//			$('.basic_vimeo_current').html(cur_h + ':' + zero_pad(cur_min) + ':' + zero_pad(cur_sec) + '/' 
//					+ dur_h + ':' + zero_pad(dur_min) + ':' + zero_pad(dur_sec))

		$('.basic_vimeo_current', $vimeo).html(zero_pad(cur_min) + ':' + zero_pad(cur_sec) + '/' + zero_pad(dur_min) + ':' + zero_pad(dur_sec))
		
		if ($vimeo.data('subs')){
		
			var stop_time = $('.basic_vimeo_subtitles', $vimeo).data('stop_time')
			if (stop_time && stop_time <= e.seconds){
				$('.basic_vimeo_subtitles', $vimeo).data('stop_time', 0)
				$('.basic_vimeo_subtitles', $vimeo).html('')
			}
		
			if ($vimeo.data('subs')[$vimeo.data('next_subs')]['start_time'] <= e.seconds){
				$('.basic_vimeo_subtitles', $vimeo).html($vimeo.data('subs')[$vimeo.data('next_subs')]['text'])
				$('.basic_vimeo_subtitles', $vimeo).data('stop_time', $vimeo.data('subs')[$vimeo.data('next_subs')]['stop_time'])
				$vimeo.data('next_subs', $vimeo.data('next_subs') + 1)
			}

		} else if (!$vimeo.data('subsloading') && $vimeo.data('subsfile')){
			$vimeo.data('subsloading', true)
			fetch($vimeo.data('subsfile')).then(response => response.json().then(function(data) {
				$vimeo.data('subs', data)
  				$vimeo.data('subsloading', false)
//        				console.log(data)
				}))
		}
		
	})
	
}

function basic_vimeo_toggle_sound($vimeo){
	if ($vimeo.data('sound')){
		$vimeo.data('sound', 0)
		$vimeo.data('player').setVolume(0)
		$vimeo.addClass('basic_vimeo_sound_is_off').removeClass('basic_vimeo_sound_is_on')
	} else {
		$vimeo.data('sound', 1)
		$vimeo.data('player').setVolume($vimeo.data('volume'))
		$vimeo.addClass('basic_vimeo_sound_is_on').removeClass('basic_vimeo_sound_is_off')
	}
}

function basic_vimeo_play($vimeo){
	
	$vimeo.data('player').play().then(() => {
		$vimeo.data('play', 1)
		$('.basic_vimeo_iframe', $vimeo).css({'opacity': 1})
		$vimeo.addClass('basic_vimeo_play_is_on').removeClass('basic_vimeo_play_is_off')
		if ($vimeo.data('sound')){
			$vimeo.data('player').setVolume($vimeo.data('volume'))
		}
		$('.basic_vimeo_start', $vimeo).remove();
	})

}

function basic_vimeo_pause($vimeo){
	
	$vimeo.data('player').pause().then(() => {
		$vimeo.data('play', 0)
		$vimeo.addClass('basic_vimeo_play_is_off').removeClass('basic_vimeo_play_is_on')
	})

}

function basic_vimeo_sound_on($vimeo){
	
}

function basic_vimeo_stopall(){

	var $vimeos = $('.basic_vimeo_container')
	
	$vimeos.each(function(){
		basic_vimeo_pause($(this))
	})
	
	return new Promise ((resolve, reject) => {
	
		var ok = true
		var interval = setInterval(() => {
			
			$vimeos.each(function(){
				if($(this).hasClass('basic_vimeo_play_is_on')){
					ok = false
				}
			})
			
			if (ok){
				clearInterval(interval)
				resolve()
			}
			
		}, 50)

	})
	
}

function basic_vimeo_resize(){
	
//	$('.basic_vimeo_iframe').css({'height': $(window).innerWidth() * 360 / 640 + 'px'})

}

function basic_vimeo_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', function(){
		basic_vimeo_resize();
	});
	
	$(window).on('scroll.cms', function(){
		basic_vimeo_scroll();
	});
	
	basic_vimeo_init();

	basic_vimeo_resize();
	
	basic_vimeo_scroll();

});
