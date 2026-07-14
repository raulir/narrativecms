var video_debug = false

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

	var play_promise = $video_el[0].play()
	if (play_promise && typeof play_promise.catch === 'function'){
		play_promise.catch(function(){})
	}
	
}

// Shared dash.js load queue for SPA / ajax-injected video (image selector, etc.)
var cms_video_dash_load_queue = null

function cms_video_dash_script_url(){

	var base = (typeof _cms_base !== 'undefined' && _cms_base) ? String(_cms_base) : '/'
	if (base.slice(-1) !== '/'){
		base += '/'
	}

	return base + 'modules/cms/js/dash/dash.min.js'

}

function cms_video_ensure_dashjs(callback){

	// callback(true) when dashjs is available; callback(false) if load fails
	if (typeof dashjs !== 'undefined'){
		callback(true)
		return
	}

	if (cms_video_dash_load_queue){
		cms_video_dash_load_queue.push(callback)
		return
	}

	cms_video_dash_load_queue = [callback]

	var finish = function(ok){

		var queue = cms_video_dash_load_queue || []
		cms_video_dash_load_queue = null
		queue.forEach(function(cb){
			cb(!!ok && typeof dashjs !== 'undefined')
		})

	}

	// Already injecting (another tag from packer / concurrent call)
	var existing = document.querySelector('script[src*="dash/dash.min.js"],script[src*="dash.min.js"]')
	if (existing){
		cms_video_waitdash_poll(finish, 10000, 20)
		return
	}

	var script = document.createElement('script')
	script.src = cms_video_dash_script_url()
	script.async = true
	script.onload = function(){
		// give UMD a tick to assign global
		setTimeout(function(){
			finish(typeof dashjs !== 'undefined')
		}, 0)
	}
	script.onerror = function(){
		if (video_debug) console.log('Failed to load dash.min.js from', script.src)
		finish(false)
	}
	document.head.appendChild(script)

}

function cms_video_waitdash_poll(callback, timeout, interval){

	timeout = timeout || 10000
	interval = interval || 20

	if (typeof dashjs !== 'undefined'){
		callback(true)
		return
	}

	var elapsed = 0
	var timer = setInterval(function(){
		elapsed += interval
		if (typeof dashjs !== 'undefined'){
			clearInterval(timer)
			callback(true)
		} else if (elapsed >= timeout){
			clearInterval(timer)
			callback(false)
		}
	}, interval)

}

function cms_video_waitdash(callback, timeout, interval){

	// Ensures dashjs is loaded (dynamic script if missing), then callback(true|false)
	cms_video_ensure_dashjs(function(ok){
		if (ok || typeof dashjs !== 'undefined'){
			callback(true)
			return
		}
		// Script may still be parsing — short poll before giving up
		cms_video_waitdash_poll(callback, timeout || 3000, interval || 20)
	})

}

function cms_video_safe_play($video_el){

	var el = $video_el && $video_el[0]
	if (!el){
		return
	}

	var play_promise = el.play()
	if (play_promise && typeof play_promise.catch === 'function'){
		play_promise.catch(function(){})
	}

}

function cms_video_copy_playback_attrs($from, $to){

	$.each($from[0].attributes, function(){
		if (this.name.indexOf('data-cms_video') === 0 && this.name !== 'data-cms_video_view'){
			$to.attr(this.name, this.value)
		}
	})

}

var cms_video_warden_stall_ms = 2500
var cms_video_warden_tick_ms = 1500
var cms_video_warden_hard_min_ms = 30000
var cms_video_visibility_bound = false

function cms_video_warden_should_watch($host, $video_el){

	var el = $video_el && $video_el[0]
	if (!$host || !$host.length || !el){
		return false
	}

	if ($host.closest('.cms_image_container').length){
		return false
	}

	if (!$host.hasClass('cms_video_ready')){
		return false
	}

	if ($host.data('cms_video_viewport_paused') == 1){
		return false
	}

	if (document.visibilityState !== 'visible'){
		return false
	}

	if (!el.muted){
		return false
	}

	return true

}

function cms_video_warden_recover($video_el, level){

	var el = $video_el[0]
	if (!el){
		return false
	}

	var saved_time = el.currentTime || 0

	if (level === 'nudge'){
		if (el.duration && !isNaN(el.duration) && saved_time >= el.duration - 0.05){
			saved_time = 0
		} else {
			saved_time = saved_time + 0.001
		}
		try {
			el.currentTime = saved_time
		} catch (e){
		}
		cms_video_safe_play($video_el)
		return true
	}

	if (level === 'hard'){
		var last_hard = $video_el.data('cms_video_warden_last_hard') || 0
		if (Date.now() - last_hard < cms_video_warden_hard_min_ms){
			return false
		}
		$video_el.data('cms_video_warden_last_hard', Date.now())

		var src = el.currentSrc || el.src
		if (!src){
			return false
		}

		var restore_time = saved_time

		var restore_and_play = function(){
			if (restore_time > 0 && el.duration && !isNaN(el.duration)){
				try {
					el.currentTime = Math.min(restore_time, el.duration - 0.05)
				} catch (e){
				}
			}
			cms_video_safe_play($video_el)
			if (el.player && typeof el.player.play === 'function'){
				el.player.play()
			}
		}

		$video_el.one('loadedmetadata.cms_video_warden_hard', restore_and_play)

		el.src = src
		el.load()

		if (el.readyState >= 1){
			restore_and_play()
		}

		return true
	}

	cms_video_safe_play($video_el)
	return true

}

function cms_video_warden_attempt_recover($video_el, $host, reason){

	if (!cms_video_warden_should_watch($host, $video_el)){
		return
	}

	var fail_count = ($video_el.data('cms_video_warden_fail_count') || 0) + 1
	$video_el.data('cms_video_warden_fail_count', fail_count)

	if (video_debug){
		console.log('cms_video warden recover', reason, fail_count, $video_el[0].currentTime)
	}

	if (fail_count < 3){
		cms_video_warden_recover($video_el, 'soft')
	} else if (fail_count < 6){
		cms_video_warden_recover($video_el, 'nudge')
	} else {
		cms_video_warden_recover($video_el, 'hard')
		$video_el.data('cms_video_warden_fail_count', 0)
	}

}

function cms_video_warden_tick($video_el, $host){

	if (!cms_video_warden_should_watch($host, $video_el)){
		return
	}

	var el = $video_el[0]
	var now = Date.now()
	var last_advance = $video_el.data('cms_video_warden_last_advance_at') || now
	var last_time = $video_el.data('cms_video_warden_last_time')

	if (el.paused || el.ended){
		cms_video_warden_attempt_recover($video_el, $host, 'paused')
		return
	}

	if (last_time !== undefined && el.currentTime === last_time && now - last_advance > cms_video_warden_stall_ms){
		cms_video_warden_attempt_recover($video_el, $host, 'frozen')
	}

}

function cms_video_viewport_detach($host){

	var observer = $host.data('cms_video_viewport_observer')
	if (observer && typeof observer.disconnect === 'function'){
		observer.disconnect()
	}
	$host.removeData('cms_video_viewport_observer cms_video_viewport_paused')

}

function cms_video_viewport_attach($host, $video_el){

	if (typeof IntersectionObserver === 'undefined'){
		return
	}

	cms_video_viewport_detach($host)

	var observer = new IntersectionObserver(function(entries){

		entries.forEach(function(entry){

			if (entry.target !== $host[0]){
				return
			}

			if (entry.isIntersecting && entry.intersectionRatio >= 0.1){
				$host.data('cms_video_viewport_paused', 0)
				if ($host.hasClass('cms_video_ready')){
					cms_video_safe_play($video_el)
				}
			} else {
				$host.data('cms_video_viewport_paused', 1)
				if ($video_el[0]){
					$video_el[0].pause()
				}
			}

		})

	}, { threshold: [0, 0.1, 0.25] })

	observer.observe($host[0])
	$host.data('cms_video_viewport_observer', observer)

}

function cms_video_warden_detach($video_el, $host){

	if ($host && $host.length){
		cms_video_viewport_detach($host)
	}

	var timer = $video_el.data('cms_video_warden_timer')
	if (timer){
		clearInterval(timer)
	}

	$video_el.off('.cms_video_warden')
	$video_el.removeData('cms_video_warden_timer cms_video_warden_last_time cms_video_warden_last_advance_at cms_video_warden_fail_count cms_video_warden_last_hard')

}

function cms_video_warden_attach($video_el, $container){

	var $host = cms_video_playback_host($container)
	if (!$host.length){
		$host = $container
	}

	if ($host.closest('.cms_image_container').length){
		return
	}

	cms_video_warden_detach($video_el, $host)

	var now = Date.now()
	$video_el.data('cms_video_warden_last_advance_at', now)
	if ($video_el[0]){
		$video_el.data('cms_video_warden_last_time', $video_el[0].currentTime)
	}

	$video_el.on('timeupdate.cms_video_warden', function(){
		var t = this.currentTime
		if ($video_el.data('cms_video_warden_last_time') !== t){
			$video_el.data('cms_video_warden_last_time', t)
			$video_el.data('cms_video_warden_last_advance_at', Date.now())
			$video_el.data('cms_video_warden_fail_count', 0)
		}
	})

	$video_el.on('pause.cms_video_warden stalled.cms_video_warden waiting.cms_video_warden suspend.cms_video_warden', function(){
		setTimeout(function(){
			cms_video_warden_tick($video_el, $host)
		}, 200)
	})

	var tick_timer = setInterval(function(){
		cms_video_warden_tick($video_el, $host)
	}, cms_video_warden_tick_ms)

	$video_el.data('cms_video_warden_timer', tick_timer)

	cms_video_viewport_attach($host, $video_el)

}

function cms_video_bind_visibility(){

	if (cms_video_visibility_bound){
		return
	}

	cms_video_visibility_bound = true

	$(document).on('visibilitychange.cms_video', function(){
		if (document.visibilityState === 'visible'){
			cms_video_resume_visible()
		}
	})

}

function cms_video_resume_visible(){

	$('[data-cms_video]').filter(function(){
		return cms_video_host_is_init_root(this) && $(this).hasClass('cms_video_ok') && $(this).hasClass('cms_video_ready')
	}).each(function(){

		var $host = $(this)
		if ($host.data('cms_video_viewport_paused') == 1){
			return
		}

		cms_video_safe_play($host.find('video.cms_video_player'))

	})

}

function cms_video_cleanup($element) {

	$element.find('[data-cms_video]').addBack('[data-cms_video]').each(function(){

		var $host = $(this)
		var timer = $host.data('cms_video_reveal_timer')
		var min_timer = $host.data('cms_video_reveal_min_timer')

		if (timer){
			clearTimeout(timer)
		}

		if (min_timer){
			clearTimeout(min_timer)
		}

		cms_video_viewport_detach($host)

		$host.removeClass('cms_video_pending cms_video_ready cms_video_ok')
		$host.removeData('cms_video_view_ready cms_video_player_ready cms_video_bg_size_raw cms_video_reveal_timer cms_video_reveal_min_timer cms_video_pending_since')

	})

    $element.find('video.cms_video_player').each(function() {
		cms_video_warden_detach($(this), cms_video_playback_host($(this)))
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

function cms_video_playback_host($container){

	var $view_host = $container.closest('[data-cms_video_view="1"]')
	if ($view_host.length){
		return $view_host
	}

	return $container.closest('[data-cms_video]')

}

var cms_video_reveal_max_ms = 3000
var cms_video_reveal_min_ms = 200

function cms_video_clear_poster($host){

	$host.css({'background-image': ''})

}

function cms_video_mark_pending($host){

	if ($host.hasClass('cms_video_ready')){
		return
	}

	$host.addClass('cms_video_pending').removeClass('cms_video_ready')
	$host.data('cms_video_pending_since', Date.now())

	if ($host.data('cms_video_reveal_timer')){
		return
	}

	var timer = setTimeout(function(){
		cms_video_reveal($host)
	}, cms_video_reveal_max_ms)

	$host.data('cms_video_reveal_timer', timer)

}

function cms_video_reveal($host){

	if ($host.hasClass('cms_video_ready')){
		return
	}

	var timer = $host.data('cms_video_reveal_timer')
	if (timer){
		clearTimeout(timer)
		$host.removeData('cms_video_reveal_timer')
	}

	var min_timer = $host.data('cms_video_reveal_min_timer')
	if (min_timer){
		clearTimeout(min_timer)
		$host.removeData('cms_video_reveal_min_timer')
	}

	$host.removeData('cms_video_pending_since')
	$host.removeClass('cms_video_pending').addClass('cms_video_ready')
	cms_video_clear_poster($host)

	$host.find('.cms_video_view_clip, .cms_video').css({
		opacity: '',
		visibility: '',
	})

	if ($host.data('cms_video_view') == 1){
		if (typeof cms_media_view_relayout === 'function'){
			cms_media_view_relayout($host)
		}
	} else {
		cms_video_apply_plain_styles($host)
	}

	cms_video_safe_play($host.find('video.cms_video_player'))

}

function cms_video_host_is_init_root(el){

	var $el = $(el)

	if ($el.closest('.cms_image_container').length){
		return false
	}

	if ($el.closest('[data-cms_video_view="1"]').length && !$el.is('[data-cms_video_view="1"]')){
		return false
	}

	return true

}

function cms_video_try_reveal($host){

	if (!$host.length || $host.hasClass('cms_video_ready')){
		return
	}

	if (!$host.hasClass('cms_video_pending')){
		return
	}

	var $video = $host.find('video.cms_video_player')

	if ($host.data('cms_video_view') == 1){

		if (typeof cms_media_view_read_meta !== 'function' || typeof cms_media_view_host_metrics !== 'function' || typeof cms_media_view_layout !== 'function'){
			return
		}

		var meta = cms_media_view_read_meta($host)
		var host_fit = cms_media_view_host_metrics($host)
		var layout = cms_media_view_layout(meta, host_fit.w, host_fit.h, host_fit)

		if (!layout || layout.crop_w < 1 || layout.crop_h < 1 || host_fit.w < 1 || host_fit.h < 1){
			return
		}

		if (!$video.length){
			return
		}

	} else {

		if ($host.outerWidth() < 1 || $host.outerHeight() < 1){
			return
		}

		if (!$video.length){
			return
		}

		cms_video_apply_plain_styles($host)

	}

	var pending_since = $host.data('cms_video_pending_since') || Date.now()
	var elapsed = Date.now() - pending_since

	if (elapsed < cms_video_reveal_min_ms){

		if (!$host.data('cms_video_reveal_min_timer')){
			var wait = cms_video_reveal_min_ms - elapsed
			var min_timer = setTimeout(function(){
				$host.removeData('cms_video_reveal_min_timer')
				cms_video_try_reveal($host)
			}, wait)
			$host.data('cms_video_reveal_min_timer', min_timer)
		}

		return

	}

	cms_video_reveal($host)

}

function cms_video_wrapper($container, $style_host, options){

	options = options || {}
	$style_host = $style_host || $container

	var $video_wrapper = $('<div class="cms_video">')
		.css({
			width: '100%',
			height: '100%',
			position: 'relative',
			overflow: 'hidden',
		})

	var video_styles

	if (options.force_fill){
		video_styles = {
			position: 'absolute',
			left: 0,
			top: 0,
			width: '100%',
			height: '100%',
			'object-fit': 'fill',
		}
	} else if (typeof cms_media_view_plain_video_styles === 'function'){
		video_styles = cms_media_view_plain_video_styles($style_host, 0, 0)
	} else {
		video_styles = {
			position: 'absolute',
			left: '50%',
			top: '50%',
			transform: 'translate(-50%, -50%)',
			width: '100%',
			height: '100%',
			'object-fit': 'cover',
		}
	}

	var poster = $style_host.data('cms_video_poster') || $container.data('cms_video_poster') || ''

	var $video = $('<video class="cms_video_player" autoplay="autoplay" muted="muted" loop="loop" playsinline="playsinline" poster="'
		+ poster + '">')
		.css(video_styles)

	$video_wrapper.append($video)

	return $video_wrapper

}

function cms_video_apply_plain_styles($host){

	if (typeof cms_media_view_plain_video_styles !== 'function'){
		return
	}

	var $video = $host.find('video.cms_video_player')
	if (!$video.length){
		return
	}

	var vw = $video[0].videoWidth || 0
	var vh = $video[0].videoHeight || 0
	var styles = cms_media_view_plain_video_styles($host, vw, vh)

	$video.css(styles)

}

function cms_video_is_iphone(){
	return /iPhone|iPod/.test(navigator.userAgent) || navigator.platform === 'iPhone'
}

function cms_video_init_player($container){

	var $video_el = $container.find('video.cms_video_player')

	if (!$video_el.length){
		var $style_host = cms_video_playback_host($container)
		if (!$style_host.length){
			$style_host = $container
		}
		var wrapper_opts = $container.hasClass('cms_video_view_source') ? { force_fill: 1 } : {}
		$container.append(cms_video_wrapper($container, $style_host, wrapper_opts))
		$video_el = $container.find('video.cms_video_player')
	}

	if ($container.data('cms_video_player_ready')){
		cms_video_safe_play($video_el)
		cms_video_try_reveal(cms_video_playback_host($container))
		return
	}

	if ($container.data('cms_video_manifest') && !cms_video_is_iphone()){

		cms_video_waitdash(function(dash_ok){

			if (!dash_ok || typeof dashjs === 'undefined'){
				if (video_debug) console.log('dashjs not available — native fallback')
				cms_video_fallback($video_el, true)
				return
			}

			var video_width = parseInt($container.data('cms_video_width'))
			var player = dashjs.MediaPlayer().create()

			player.on(dashjs.MediaPlayer.events.ERROR, function(e) {
				console.error('dash.js error:', e)
				if (e.error && (
					e.error.code === dashjs.MediaPlayer.errors.MANIFEST_PARSING_ERROR ||
					e.error.code === dashjs.MediaPlayer.errors.MANIFEST_LOADED_WITH_ERRORS
				)) {
					console.log('Loading issue - trying native fallback')
					cms_video_fallback($video_el)
				}
			})

			player.on(dashjs.MediaPlayer.events.MANIFEST_LOADED, function () {
				setTimeout(function () {
					var bitrate_list = player.getBitrateInfoListFor('video')
					if (!bitrate_list || bitrate_list.length < 1) return

					var max_w = Math.max(video_width || 2100, 500)
					var allowed = bitrate_list.filter(function(b){
						return (b.width || 0) <= max_w
					})
					if (allowed.length === 0) allowed = bitrate_list

					var best_index = 0
					var best_bitrate = 0
					allowed.forEach(function(b){
						if (b.bitrate > best_bitrate){
							best_bitrate = b.bitrate
							best_index = bitrate_list.indexOf(b)
						}
					})

					setTimeout(function () {
						player.setQualityFor('video', best_index)
					}, 1000)

				}, 200)
			})

			var manifest_url = $container.data('cms_video_manifest')
			player.initialize($video_el[0], manifest_url, true)

		})

	} else {

		if (video_debug) console.log('No video manifest url or iphone detected - trying native fallback')
		cms_video_fallback($video_el, true)

	}

	$video_el.off('loadedmetadata.cms_video_plain').on('loadedmetadata.cms_video_plain', function(){
		var $player_host = cms_video_playback_host($container)
		if ($player_host.length && $player_host.data('cms_video_view') != 1){
			cms_video_apply_plain_styles($player_host)
		}
		cms_video_try_reveal($player_host)
	})

	$video_el.off('loadeddata.cms_video_reveal').on('loadeddata.cms_video_reveal', function(){
		cms_video_try_reveal(cms_video_playback_host($container))
	})

	$video_el.off('playing.cms_video_reveal').on('playing.cms_video_reveal', function(){
		cms_video_try_reveal(cms_video_playback_host($container))
	})

	$container.data('cms_video_player_ready', 1)

	cms_video_warden_attach($video_el, $container)

}

function cms_video_init_plain($host){

	if ($host.hasClass('cms_video_ok')){
		return
	}

	cms_video_mark_pending($host)
	$host.empty().append(cms_video_wrapper($host, $host))
	cms_video_init_player($host)
	$host.addClass('cms_video_ok')

	setTimeout(function(){
		cms_video_try_reveal($host)
	}, cms_video_reveal_min_ms)

}

function cms_video_init_view($host){

	if ($host.data('cms_video_view_ready')){
		if (!$host.hasClass('cms_video_ready')){
			cms_video_mark_pending($host)
		}
		if (typeof cms_media_view_relayout === 'function'){
			cms_media_view_relayout($host)
		}
		cms_video_safe_play($host.find('video.cms_video_player'))
		cms_video_try_reveal($host)
		return
	}

	cms_video_mark_pending($host)

	var $clip = $('<div class="cms_video_view_clip">')
	var $crop = $('<div class="cms_video_view_crop">')
	var $pan = $('<div class="cms_video_view_pan">')
	var $rotate = $('<div class="cms_video_view_rotate">')
	var $source = $('<div class="cms_video_view_source">')
	var $overlay = $('<div class="cms_video_view_overlay">')

	$pan.append($rotate)
	$rotate.append($source)
	$crop.append($pan)
	$clip.append($crop).append($overlay)
	$host.empty().append($clip)

	cms_video_copy_playback_attrs($host, $source)

	$source.append(cms_video_wrapper($source, $host, { force_fill: 1 }))
	var $video_el = $source.find('video.cms_video_player')

	$video_el.off('loadedmetadata.cms_video_view').on('loadedmetadata.cms_video_view', function(){
		if (this.videoWidth && this.videoHeight){
			$host.data('source_w', this.videoWidth)
			$host.data('source_h', this.videoHeight)
			if (typeof cms_media_view_relayout === 'function'){
				cms_media_view_relayout($host)
			}
		}
		cms_video_try_reveal($host)
	})

	cms_video_init_player($source)

	if (typeof cms_media_view_relayout === 'function'){
		cms_media_view_relayout($host)
	}

	$host.data('cms_video_view_ready', 1)
	$host.addClass('cms_video_ok')

	setTimeout(function(){
		cms_video_try_reveal($host)
	}, cms_video_reveal_min_ms)

}

function cms_video_init($root){

	var $cms_video

	if ($root){
		$cms_video = $root.find('[data-cms_video]').filter(function(){
			return cms_video_host_is_init_root(this)
		})
	} else {
		$cms_video = $('[data-cms_video]').filter(function(){
			return cms_video_host_is_init_root(this)
		})
	}

	$cms_video.not('.cms_video_ok').each(function() {

		var $this = $(this)

		if ($this.data('cms_video_view') == 1){
			cms_video_init_view($this)
		} else {
			cms_video_init_plain($this)
		}

	})

}

function cms_video_destroy($root){

	var $scope = $root || $(document)

	cms_video_cleanup($scope)

}

function cms_video_resume_all($root){

	$root = $root || $(document)

	$root.find('[data-cms_video]').filter(function(){
		return cms_video_host_is_init_root(this) && $(this).hasClass('cms_video_ok')
	}).each(function(){

		var $host = $(this)

		if ($host.data('cms_video_viewport_paused') == 1){
			return
		}

		if ($host.data('cms_video_view') == 1){
			if (typeof cms_media_view_relayout === 'function'){
				cms_media_view_relayout($host)
			}
		} else {
			cms_video_apply_plain_styles($host)
		}

		cms_video_safe_play($host.find('video.cms_video_player'))
		cms_video_try_reveal($host)

	})

}

function cms_video_init_when_ready($root, callback){

	$root = $root || $(document)
	var attempts = 0
	var max_attempts = 120

	function has_view_hosts(){

		return $root.find('[data-cms_video_view="1"]').filter(function(){
			return cms_video_host_is_init_root(this)
		}).length > 0

	}

	function try_run(){

		attempts++

		if (typeof cms_video_init !== 'function'){
			if (attempts < max_attempts){
				setTimeout(try_run, 50)
			}
			return
		}

		if (has_view_hosts() && typeof cms_media_view_read_meta !== 'function'){
			if (attempts < max_attempts){
				setTimeout(try_run, 50)
			}
			return
		}

		cms_video_init()

		if (typeof cms_video_resize === 'function'){
			cms_video_resize()
		}

		cms_video_resume_all($root)

		if (typeof callback === 'function'){
			callback()
		}

	}

	try_run()

}

function cms_video_resize(){

	$('[data-cms_video]').filter(function(){
		return cms_video_host_is_init_root(this) && $(this).hasClass('cms_video_ok')
	}).each(function(){

		var $host = $(this)

		if ($host.data('cms_video_view') == 1){
			if (typeof cms_media_view_relayout === 'function'){
				cms_media_view_relayout($host)
			}
		} else {
			cms_video_apply_plain_styles($host)
		}

	})

}

function cms_video_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', cms_video_resize)
	$(window).on('scroll.cms', cms_video_scroll)
	cms_video_bind_visibility()
	
	cms_video_init_when_ready()
	cms_video_scroll()

})
