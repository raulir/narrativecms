function audioplayer_format_time(seconds, after){
	
	var mins = Math.floor(seconds/60);
	var secs = (Math.floor((seconds - mins*60)* 10)/10).toFixed(after);
	
	var zeroPad = function(number, size) {
	    var s = String(number);
	    while (s.length < (size || 2)) {s = '0' + s;}
	    return s;
	}

	var timestr = '';
	if (mins || after == 0){
		timestr = mins + ':' + zeroPad(secs, (after > 0 ? after + 3 : 0));
	} else {
		timestr = secs;
	}

	return timestr
	
}

function audioplayer_init(){

	$('.audioplayer_container').each(function(){

		var $this = $(this);

		// to avoid double inits
		if ($this.data('cms_init') === true){
			return;
		}
		$this.data('cms_init', true);

		var $audio = $('.audioplayer_audio_audio', $this);
		var $bar = $('.audioplayer_bar', $this);
		var $progress = $('.audioplayer_progress', $this);
		
		var $current = $('.audioplayer_current', $this);
		var $seek = $('.audioplayer_seek', $this);
		
		if ($audio.length){

			$audio.get(0).currentTime = 0;

			$audio.off('timeupdate.cms').on('timeupdate.cms', function(){
				
				var audio = $(this).get(0);
				var x = (audio.currentTime / audio.duration);

				$bar.css({'width': x * 100 + '%'});
				
				$current.css({'left':'calc(' + (x * $progress.outerWidth()) + 'px - 1.5rem)'});

				if (audio.currentTime < 0.1){
					$current.css({'opacity':''});
				} else {
					$current.css({'opacity':'0.5'});
				}
								
				$current.html(audioplayer_format_time(audio.currentTime, 0));
				
				// analytics
				var update = (Math.floor((audio.currentTime / audio.duration) * 20) * 5);
				if (!$audio.data('ga_' + update) && update < 100){
					if (typeof ga_cms !== 'undefined'){
						ga_cms('send', 'event', 'Audioplayer', 'audioplayer_progress_' + $this.data('cms_page_panel_id') + '_' + update);
					}
					$audio.data('ga_' + update, true);
				}
				
			});

			$audio.off('ended.cms').on('ended.cms', function(){
				$audio.get(0).muted = true;
				$('.audioplayer_control', $this).removeClass('audioplayer_control_active');
				if (typeof ga_cms !== 'undefined'){
					ga_cms('send', 'event', 'Audioplayer', 'audioplayer_progress_' + $this.data('cms_page_panel_id') + '_100');
				}
			});
			
			$('.audioplayer_copy', $this).off('click.cms').on('click.cms', function(){
				$('.audioplayer_play', $this).click();
			});

			$('.audioplayer_play', $this).off('click.cms').on('click.cms', function(){
				
				$audio.get(0).play();
				$audio.get(0).muted = false;
				$('.audioplayer_control', $this).addClass('audioplayer_control_active');
				
				if (typeof ga_cms !== 'undefined'){
					ga_cms('send', 'event', 'Audioplayer', 'audioplayer_play_' + $this.data('cms_page_panel_id'));
					ga_cms('send', 'pageview', location.pathname.replace(/\/$/, '') + '/audioplayer_' + $this.data('cms_page_panel_id') + '/');
				}
				
			});
			
			$('.audioplayer_pause', $this).off('click.cms').on('click.cms', function(){
				$audio.get(0).pause();
				$audio.get(0).muted = true;
				$('.audioplayer_control', $this).removeClass('audioplayer_control_active');
				if (typeof ga_cms !== 'undefined'){
					ga_cms('send', 'event', 'Audioplayer', 'audioplayer_pause_' + $this.data('cms_page_panel_id'));
				}
			});
			
			$('.audioplayer_progress', $this).off('click.cms').on('click.cms', function(e){
				
				if (typeof e.offsetX == 'undefined'){
					var x = (e.touches[0].pageX - e.touches[0].target.offsetLeft) / $(this).width();
				} else {
					var x = (e.pageX - $(this).offset().left) / $(this).width();
				}
				
				if (x > 1) x = 1;
				if (x < 0) x = 0;

				
				$audio.get(0).currentTime = $audio.get(0).duration * x;
				
				if (typeof ga_cms !== 'undefined'){
					ga_cms('send', 'event', 'Audioplayer', 'audioplayer_seek_' + $this.data('cms_page_panel_id') + '_' + Math.round(pc));
				}
				
			});
			
			$('.audioplayer_progress', $this).off('mousemove.cms').on('mousemove.cms', function(e){

				var x = (e.pageX - $(this).offset().left) / $(this).width();
				if (x > 1) x = 1;
				if (x < 0) x = 0;
				
				var time = $audio.get(0).duration * x;

				$seek.css({'left':'calc(' + (x * $(this).width())  + 'px - 1.5rem)'}).html(audioplayer_format_time(time, 1));
				
			})

		}

	})

}

function audioplayer_resize(){
	
}

$(document).ready(function(){
	
	$(window).on('resize.cms', audioplayer_resize);

	audioplayer_init();
	
	audioplayer_resize();

})

