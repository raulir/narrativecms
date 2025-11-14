
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

function video_init(){
	
	var $cms_video = $('[data-cms_video]')
	
	$cms_video.each(function() {
		
		var $this = $(this)
		var video_url = $this.data('cms_video')
		var fit = $this.css('background-size')
		
		var $video = $('<div class="cms_video" style="width: 100%; height: 100%; position: relative; ">' 
			+ '<video style="max-width: 100%; max-height: 100%; display: block; position: absolute; left: 50%; top: 50%; '
			+ 'transform: translate(-50%, -50%); " src="' + video_url + '" '
			+ 'autoplay="autoplay" muted="muted" loop="loop" playsinline="playsinline">'
			+ '</div>')
			
		$this.empty().append($video);
		$this.css({'background-image':''})
		
	})
	
}

function video_resize(){
	
}

function video_scroll(){
	
}

$(document).ready(function() {
	
	$(window).on('resize.cms', video_resize)
	$(window).on('scroll.cms', video_scroll)
	
	video_init()
	video_resize()
	video_scroll()

})
