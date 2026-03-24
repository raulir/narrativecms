var video_debug = true

function video_play($video, params){

	if (typeof (params.start) == 'undefined'){
		params.start = 0.1;
	}
	if (typeof (params.after) == 'undefined'){
		params.after = function(){};
	}
	if (typeof (params.end) == 'undefined' || params.end == 0){
		params.end = 100000;
	}
	
	$video.siblings('.carousel_video_cover').animate({'opacity':'0'}, 250);
	
	if($video[0].readyState == 4 && $video.data('failed') != 1){
		video_resize($video);
		$video.css({'display':'block'}).animate({'opacity':'1'}, 250);
	} else {
		$video.off('loadedmetadata.cms').on('loadedmetadata.cms', function(){
	
			$video.off('loadedmetadata.cms');
			video_resize($video);
			$video.css({'display':'block'}).animate({'opacity':'1'}, 250);
			
		});
		$video.data('failed', 0);
		$video.get(0).load();
	}
	
	$video.prop('volume', 0);
	
	if($video[0].readyState > 0){
		$video[0].currentTime = params.start;
	} else {
		$video.off('loadedmetadata.cms_starttime').on('loadedmetadata.cms_starttime', function(){
			this.currentTime = params.start;
			$video.off('loadedmetadata.cms_starttime');
		});
	}
	
	var video_start = -1;
	var videointerval = setInterval(function(){
//		$('.carousel_video_cover').html($('.carousel_video_cover').html() + ' <span style="color: yellow; ">' + $video[0].readyState + '</span>');
		if($video[0].readyState == 4){
			
			$video.css({'display':'block'}).animate({'opacity':'1'}, 250);
			$video.get(0).play();
			if (video_start == -1){
				video_start = $video[0].currentTime;
			}
			clearInterval(videointerval);
			
			var video_i = 0;
			var videointerval2 = setInterval(function(){
				if ($video[0].currentTime > video_start){
					video_start_playing($video, params);
					clearInterval(videointerval2);
				} else {
					$video.data('failed', 1);
//					$('.carousel_video_cover').html($('.carousel_video_cover').html() + ' <span style="color: yellow; ">.</span>');
					
					video_show_button($video, params);
					
					video_i = video_i + 1;
					if (video_i > 3){
						clearInterval(videointerval2);
						video_end_playing($video, params);
					}
				}
			}, 3000);
		
		}
	}, 300);
	
}

function video_show_button($video, params){
	
	$video.data('failed', 0);
	
	var $cover = $video.siblings('.carousel_video_cover');
	if (parseInt($cover.css('opacity')) == 0){
		$cover.animate({'opacity':'1'}, 250);
	}
	
	$cover.off('click.cms').on('click.cms', function(){
		$cover.animate({'opacity':'0'}, 250);
		video_play($video, params);
	});
	
}

function video_end_playing($video, params){
	$video.off('timeupdate.cms').off('ended.cms');
	$video.siblings('.carousel_video_cover').css({'opacity':''});
	params.after();
}

function video_start_playing($video, params){

	$video.off('ended.cms').on('ended.cms', function(){
		this.pause();
		video_end_playing($(this), params);
	});

	$video.off('timeupdate.cms').on('timeupdate.cms', function(){
		if (this.currentTime > params.end || this.currentTime > this.duration - 0.3){
			this.pause();
			video_end_playing($(this), params);
		}
	});

}

function video_resize($video){
	
	$video.css({'display':'block'});
	
	var aheight = $video.parent().height();
	var awidth = $video.parent().width();

	var ratio = aheight / awidth;
	if ($video.length){
		var vheight = $video[0].videoHeight;
		var vwidth = $video[0].videoWidth;
		var video_ratio = vheight / vwidth;

		if (video_ratio){
			if (video_ratio < ratio){
				var newheight = aheight;
				var newwidth = newheight / video_ratio;
			} else {
				var newwidth = awidth;
				var newheight = newwidth * video_ratio;
			}
			$video.css({
				'width': newwidth + 'px', 
				'height': newheight + 'px',
				'left' : (awidth - newwidth)/2 + 'px',
				'top' : (aheight - newheight)/2 + 'px'
			});
//			$video.css({'width': $video.width() + 'px', 'height': $video.height() + 'px'});
		}
	}

}

function video_pause($video){
	
	$video.get(0).pause();
	$video.animate({'opacity':'0'}, 250);
	
}

function cms_video_fallback($video_el, higher_quality = false){

	var url = $video_el.closest('[data-cms_video]').data('cms_video')
	
	if (higher_quality){
		url = $video_el.closest('[data-cms_video]').data('cms_video_hd')
	}
	
	$video_el.attr('src', url)
	$video_el[0].play()
	
}

function cms_video_waitdash(callback, timeout = 5000, interval = 20) {

	if (typeof dashjs !== 'undefined') {
		callback()
		return
	}

    let elapsed = 0;
    const timer = setInterval(() => {
        elapsed += interval
        if (typeof dashjs !== 'undefined') {
            clearInterval(timer)
            callback()
        } else if (elapsed >= timeout) {
            clearInterval(timer)
            callback()
        }
    }, interval)
    
}

function cms_video_cleanup($element) {
    $element.find('video.cms_video_player').each(function() {
        if (this.player) {
            if (typeof this.player.destroy === 'function') {
                this.player.destroy()
            } else if (typeof this.player.reset === 'function') {
                this.player.reset()
            }
            this.player = null
        }
    })
}

function cms_video_wrapper($this){

	var fit = $this.css('background-size') || 'cover'

	var $video_wrapper = $('<div class="cms_video">')
        .css({
            width: '100%',
            height: '100%',
            position: 'relative',
            overflow: 'hidden'   // safety for cover
        })

    var video_styles = {
        position: 'absolute',
        left: '50%',
        top: '50%',
        transform: 'translate(-50%, -50%)',
        'max-width': 'none',     // important override
        'max-height': 'none',
    }

    if (fit === 'cover' || fit === '100% 100%' || fit === 'cover cover') {
        video_styles.width = '100%'
        video_styles.height = '100%'
        video_styles['object-fit'] = 'cover'
        // optional: read background-position later and set object-position
        // e.g. var pos = $this.css('background-position')
        // video_styles['object-position'] = pos
    } else {
        // contain or other → keep your original centred + max-*
        video_styles['max-width'] = '100%'
        video_styles['max-height'] = '100%'
        // no object-fit needed, acts like contain by default
    }
 
    var $video = $('<video class="cms_video_player" autoplay="autoplay" muted="muted" loop="loop" playsinline="playsinline" poster="'
    	+ $this.data('cms_video_poster') + '">')
        .css(video_styles)		
        
    $video_wrapper.append($video)
        	
	return $video_wrapper

}

function cms_video_is_iphone(){
	return /iPhone|iPod/.test(navigator.userAgent) || navigator.platform === 'iPhone'
}

function cms_video_init(){

	var $cms_video = $('[data-cms_video]')
	
	$cms_video.each(function() {
		
		var $this = $(this)
		
		var $video = cms_video_wrapper($this)

		if ($this.data('cms_video_manifest') && !cms_video_is_iphone()){

			cms_video_waitdash(() => {

				$this.css({'background-image':''})
				$this.empty().append($video);
				var $video_el = $('video', $this)
			
				var video_width = parseInt($this.data('cms_video_width'))
		
				var player = dashjs.MediaPlayer().create()

				player.on(dashjs.MediaPlayer.events.ERROR, function(e) {
                	console.error('dash.js error:', e)
                	if (e.error.code === dashjs.MediaPlayer.errors.MANIFEST_PARSING_ERROR ||
                    	e.error.code === dashjs.MediaPlayer.errors.MANIFEST_LOADED_WITH_ERRORS) {
	                    console.log('Loading issue - trying native fallback')
	                    cms_video_fallback($video_el)
	                }
            	})

				/*== cms video quality selector by container width ==*/
				
				player.on(dashjs.MediaPlayer.events.MANIFEST_LOADED, function () {
					setTimeout(function () {
						let bitrate_list = player.getBitrateInfoListFor('video')
						if (!bitrate_list || bitrate_list.length < 1) return
				
						// start at lowest quality (chunk-0*) for instant playback
//						player.setQualityFor('video', 0)
				
						// calculate highest quality allowed by container width
						let max_w = Math.max(video_width || 2100, 500)
						let allowed = bitrate_list.filter(function(b){
							return (b.width || 0) <= max_w
						})
						if (allowed.length === 0) allowed = bitrate_list
				
						let best_index = 0
						let best_bitrate = 0
						allowed.forEach(function(b){
							if (b.bitrate > best_bitrate){
								best_bitrate = b.bitrate
								best_index = bitrate_list.indexOf(b)
				        }
						})
				
						// upgrade to highest allowed quality after 1.2s
						setTimeout(function () {
							player.setQualityFor('video', best_index)
						}, 1000)
				
					}, 200)
				})

				var manifest_url = $this.data('cms_video_manifest')
            	player.initialize($video_el[0], manifest_url, true)
	        
	        }) // end of waitdash()
			
		} else {
		
			if (video_debug) console.log('No video manifest url or iphone detected - trying native fallback')
			
			$this.css({'background-image':''})
			$this.empty().append($video);
			var $video_el = $('video', $this)

	        cms_video_fallback($video_el, true)
	    
	    }

	})
	
}

function cms_video_resize(){
	
}

function cms_video_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', cms_video_resize)
	$(window).on('scroll.cms', cms_video_scroll)
	
	cms_video_init()
	cms_video_resize()
	cms_video_scroll()

})
