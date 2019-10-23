function carousel_basic_clear_progress_handlers($this){

	// remove play handler
	if ($this.data('play_handler')){
		clearTimeout($this.data('play_handler'));
		$this.data('play_handler', false);
	}
	
	// remove video ended events
	$('.carousel_basic_item_video_video', $this).each(function(){
		$(this).off('ended.cms')
	})
	
}

function carousel_basic_user_hide($this){
	
	'use strict'

	var $button = $('.carousel_basic_active', $this);
	var $slide = $('.carousel_basic_item_' + $button.data('carousel_basic_number'), $this);
	
	setTimeout(function(){
		
		var $video = $('.carousel_basic_item_video_video', $slide);
		
		if ($video.length){

			$video.get(0).muted = true;
			$video.get(0).pause();
			$video.get(0).currentTime = 0;
			$video.data('state', 'pause');
		}

	}, 100);
	
}

function carousel_basic_user_show($this){

	'use strict'
	
	carousel_basic_clear_progress_handlers($this)

	var $button = $('.carousel_basic_active', $this);
	var $slide = $('.carousel_basic_item_' + $button.data('carousel_basic_number'), $this);

	// video
	var $video = $('.carousel_basic_item_video_video', $slide);
	if ($video.length){
		
		if ($('.carousel_basic_sound_on').length){
			$video.get(0).muted = false;
		} else {
			$video.get(0).muted = true;
		}

		carousel_basic_video_play($video).then(() => {
			
			$video.on('ended.cms', function(){
console.log('video ended');
//				carousel_basic_play($this, 100);
				carousel_basic_next($this);
				
			});

		});
	
	} else {
		
		
		
	}

}

function carousel_basic_video_play($video){
	
	'use strict'
console.log('video play');
	return new Promise((resolve, reject) => {
		
		var i = 0;
		
		var interval = setInterval(() => {
			
			var ready = $video.hasClass('carousel_basic_item_video_visible')
			
			$video.get(0).muted = true;
			
			if(ready && i < 50){

				$video.get(0).currentTime = 0

				$video.get(0).play().then(() => {
console.log('play success')
setTimeout(() => {console.log($video.get(0).currentTime)}, 1000)
					clearInterval(interval)
					resolve()
					
				}, (error) => {
					
					console.log('error in play promise, forcing mute')
					
					$video.get(0).muted = true;
					$('.carousel_basic_sound_on').removeClass('carousel_basic_sound_on');

					clearInterval(interval)
					resolve()

				}).catch(error => {
					
					console.log('general play promise error', error.message)
										
				});

				$video.data('state', 'play');

			} else if (i >= 50) {
				
				clearInterval(interval)
				reject()
			
			}

			i = i + 1;
			
		}, 200)
		
	})

}

function carousel_basic_click(){
	
	return false;
	
}

function carousel_basic_init(){
	
	'use strict'

	// videos
	setTimeout(function(){

		$('.carousel_basic_item_video_video').each(function(){
			
			var $this = $(this);
			
			$this.cms_video_resize({
				'fit':'cover',
				'retry': 10,
				'after':function(){
					$this.addClass('carousel_basic_item_video_visible');
				}
			});
			
		});

	}, 100);

	$('.carousel_basic_items').each(function(){

		var $this = $(this);

		// to avoid double inits
		if ($this.hasClass('cms_init')){
			return;
		}
		$this.addClass('cms_init');

		// create carousel buttons
		$this.append('<div class="carousel_basic_buttons"></div>');
		
		var i = 0;
		$('.carousel_basic_item', $this).each(function(){
			
			$(this).css({'z-index': 50 - i}).addClass('carousel_basic_item_' + i);
			$('.carousel_basic_buttons', $this).append('<div class="carousel_basic_button carousel_basic_button_' + i + '" data-carousel_basic_number="' + i + '"></div>');
			i = i + 1;

		});

		// activate next and previous buttons
		$('.carousel_basic_arrow_right', $this.closest('.carousel_basic_carousel')).on('click.cms', function(e){
			e.stopPropagation();
//			carousel_basic_pause($this, $this.data('delay') * 4);
			carousel_basic_next($this, $this.data('delay') * 4)
		});
		$('.carousel_basic_arrow_left', $this.closest('.carousel_basic_carousel')).on('click.cms', function(e){
			e.stopPropagation();
//			carousel_basic_pause($this, $this.data('delay') * 4);
			carousel_basic_previous($this, $this.data('delay') * 4)
		});
		
		setTimeout(function(){
			
			$('.carousel_basic_carousel_hidden').removeClass('carousel_basic_carousel_hidden');
			
			// make 0th one active
			$('.carousel_basic_button_0', $this).addClass('carousel_basic_active');
			$('.carousel_basic_item_0', $this).addClass('carousel_basic_item_active');
			
			carousel_basic_cycle_arrows($this, 0)
			
console.log('show init')
			carousel_basic_show($this);

		}, 200);

		// if more than one image, init
		if ($('.carousel_basic_item', $this).length > 1){
			
			// init buttons
			$('.carousel_basic_button', $this).on('click.r', function(e){
				
				e.stopPropagation();

				if (!$(this).hasClass('carousel_basic_active')){
					
					carousel_basic_clear_progress_handlers($this)
					
					var carousel_basic_number = parseInt($(this).data('carousel_basic_number'));
					var current_number = parseInt($('.carousel_basic_active').data('carousel_basic_number'));
					
					// carousel_basic_show($this, number, reverse, speed, delay){
					if (current_number < carousel_basic_number){
						carousel_basic_show($this, carousel_basic_number, 0, parseInt($this.data('animation_speed')), $this.data('delay') * 4);
					} else {
						carousel_basic_show($this, carousel_basic_number, 1, parseInt($this.data('animation_speed')), $this.data('delay') * 4);
					}
				}
				
			});
			
			$('.carousel_basic_buttons', $this).css({'display':''});
			$('.carousel_basic_arrow_left', $this.closest('.carousel_basic_carousel')).css({'display':''});
			$('.carousel_basic_arrow_right', $this.closest('.carousel_basic_carousel')).css({'display':''});
			
			setTimeout(function(){
				$('.carousel_basic_item_hidden', $this).removeClass('carousel_basic_item_hidden');
			}, 1000);

		} else {
		
		}

		// drag
		if ($('.carousel_basic_item', $this).length > 2){
			$this.on('mousedown.r touchstart.r', function(e) {

				if ($this.data('drag_disabled') == 'on') return is_touch_event(e);

				e.stopPropagation();
				
				var $touch_area = $(this);
				if (typeof e.pageX == 'undefined'){
					var x = e.originalEvent.touches[0].pageX;
					var y = e.originalEvent.touches[0].pageY;
				} else {
					var x = e.pageX;
					var y = e.pageY;
				}
				$touch_area.data('drag_start_x', x);
				$touch_area.data('drag_start_y', y);
				$touch_area.data('drag_on', 'on');
				$touch_area.data('drag_start', +new Date());
				
				// set timeout for drag event
				$touch_area.data('drag_timeout', setTimeout(function(){
					if ($touch_area.data('drag_on') == 'on'){
						carousel_basic_dragend($this, $touch_area);
					}
				}, 1000));
				
				carousel_basic_pause($this, $this.data('delay') * 4);

				return is_touch_event(e);

			}).on('mousemove.r touchmove.r', function(e) {

				if ($this.data('drag_disabled') == 'on') return is_touch_event(e);

				e.stopPropagation();
				
				var $touch_area = $(this);
				if ($touch_area.data('drag_on') == 'on'){
					
					if (typeof e.pageX == 'undefined'){
						var x = e.originalEvent.touches[0].pageX;
						var y = e.originalEvent.touches[0].pageY;
					} else {
						var x = e.pageX;
						var y = e.pageY;
					}
					
					$touch_area.data('last_x', x);
					$touch_area.data('last_y', y);
					
					// drag carousel only on x
					var delta_x = x - parseInt($touch_area.data('drag_start_x'));
					if (delta_x > 10 || delta_x < 10){

						// move slide accordingly

						var current_number = parseInt($('.carousel_basic_active', $this).data('carousel_basic_number'));

						var previous_number = carousel_basic_get_previous($this);
						var next_number = carousel_basic_get_next($this);
						var width = $('.carousel_basic_item_' + current_number, $this).width();
						
						// if first
						if ($this.data('cycle') == 0){
						
							if (previous_number > current_number && delta_x > 30) {
								delta_x = 20;
							}
							
							// if last
							if (next_number < current_number && delta_x < -30) {
								delta_x = -20;
							}
							
						}

						// position images
						$('.carousel_basic_item_' + current_number, $this).css({'left': delta_x + 'px'});
						$('.carousel_basic_item_' + next_number, $this).css({'left': delta_x + width + 'px', 'z-index':'49', 'display':'block'});
						$('.carousel_basic_item_' + previous_number, $this).css({'left': delta_x - width + 'px', 'z-index':'49', 'display':'block'});

					}
					
				}
				
				return is_touch_event(e);

			}).on('mouseup.r touchend.r', function(e){
				
				carousel_basic_dragend($this, $(this), e);
			
			});
		}
		
	});
	
	$('.carousel_basic_sound').on('click.cms', function(){
		
		if ($(this).hasClass('carousel_basic_sound_on')){
			$(this).removeClass('carousel_basic_sound_on');
			carousel_basic_sound_off();
		} else {
			$(this).addClass('carousel_basic_sound_on');
			carousel_basic_sound_on();
		}
		
	})
	
}

function carousel_basic_dragend($this, $touch_area, e){
	
	'use strict'

	if (e){
		e.stopPropagation();
	}
	
	$touch_area.data('drag_on', 'off');
	clearTimeout($touch_area.data('drag_timeout'));

    if ($touch_area.data('drag_start_x') == 0 || typeof $touch_area.data('last_x') == 'undefined' || $touch_area.data('last_x') == 0){
    	var delta_x = 0;
    } else {
    	var delta_x = parseInt($touch_area.data('last_x')) - parseInt($touch_area.data('drag_start_x'));
    }
    
    if ($touch_area.data('drag_start_y') == 0 || typeof $touch_area.data('last_y') == 'undefined' || $touch_area.data('last_y') == 0){
    	var delta_y = 0;
    } else {
    	var delta_y = parseInt($touch_area.data('last_y')) - parseInt($touch_area.data('drag_start_y'));
    }

    $touch_area.data('drag_start_x', 0);
    $touch_area.data('last_x', 0);
    $touch_area.data('drag_start_y', 0);
    $touch_area.data('last_y', 0);

    if ($this.data('drag_disabled') == 'on') {
    	if (e){
        	return is_touch_event(e);
    	} else {
    		return false;
    	}
    }
    
	if (delta_x > 100){
		carousel_basic_previous($this, $this.data('delay') * 4);
	} else if (delta_x < -100){
		carousel_basic_next($this, $this.data('delay') * 4);
	} else {
		
		var current_number = parseInt($('.carousel_basic_active', $this).data('carousel_basic_number'));
		var $current_panel = $('.carousel_basic_item_' + current_number, $this);
		
		$current_panel.css({'left': '0px'});
		
		var delta_t = +new Date() - $touch_area.data('drag_start');

		if (delta_x < 10 && delta_x > -10 && delta_y < 10 && delta_y > -10 && 
				(typeof e !== 'undefined' && (e.type == 'touchend' || e.which == 1)) && delta_t < 200){
			
			carousel_basic_clear_progress_handlers($this)
			carousel_basic_click($this, $current_panel);
			
		}
		
	}
	
	if (e){
    	return is_touch_event(e);
	} else {
		return false;
	}

}

function carousel_basic_resize(){

	'use strict'

}

function carousel_basic_play($this, delay){
	
	'use strict'

	if (!delay) delay = $this.data('delay'); // slider autoadvance delay
	
console.log('set handler - play', delay);
	clearTimeout($this.data('play_handler'))
	$this.data('play_handler', setTimeout(function(){
//		carousel_basic_play($this);
		carousel_basic_next($this);
	}, delay));
	
}

function carousel_basic_pause($this, delay){
	
	'use strict'
console.log($this.data());
	if (!delay) delay = $this.data('delay') * 4;
console.log('set handler - pause', delay);

	clearTimeout($this.data('play_handler'))
	$this.data('play_handler', setTimeout(function(){
//		carousel_basic_play($this);
		carousel_basic_next($this);
	}, delay));
	
}

function carousel_basic_destroy($this){
	
	'use strict'

	carousel_basic_clear_progress_handlers($this)
	
}

function carousel_basic_get_next($this){

	'use strict'

	var number = parseInt($('.carousel_basic_active', $this).data('carousel_basic_number')) + 1;

	if (number >= $('.carousel_basic_button', $this).length){
		number = 0;
	}
	
	return number;
	
}

function carousel_basic_next($this, delay){
	
	'use strict'

	if ($this.data('animating')){
		return;
	}
console.log('next requested');	
	$this.data('animating', true);
	setTimeout(function(){
		$this.data('animating', false);
	}, 300);
	
	// carousel_basic_show($this, number, reverse, speed, delay){
	carousel_basic_show($this, carousel_basic_get_next($this), 0, $this.data('animation_speed'), $this.data('delay'));

}

function carousel_basic_previous($this, delay){
	
	'use strict'

	if ($this.data('animating')){
		return;
	}
	
	$this.data('animating', true);
	setTimeout(function(){
		$this.data('animating', false);
	}, 300);

	carousel_basic_show($this, carousel_basic_get_previous($this), 1, $this.data('animation_speed'), $this.data('delay'));

}

function carousel_basic_get_previous($this){

	'use strict'

	var number = parseInt($('.carousel_basic_active', $this).data('carousel_basic_number')) - 1;

	if (number < 0){
		number =  $('.carousel_basic_button', $this).length - 1;
	}
	
	return number;
	
}

function carousel_basic_cycle_arrows($this, number){
	
	'use strict'

	if ($this.data('cycle') == 0){

		// hide forward arrow
		if( number == $('.carousel_basic_button', $this).length - 1 ){
			$('.carousel_basic_arrow_right').addClass('carousel_basic_arrow_hidden');
		} else {
			$('.carousel_basic_arrow_right').removeClass('carousel_basic_arrow_hidden');
		}
		
		// hide backward arrow
		if( number == 0 ){
			$('.carousel_basic_arrow_left').addClass('carousel_basic_arrow_hidden');
		} else {
			$('.carousel_basic_arrow_left').removeClass('carousel_basic_arrow_hidden');
		}
		
	}

}

function carousel_basic_show($this, number, reverse, speed, delay){
	
	'use strict'
console.log('show clear handlers')
	carousel_basic_clear_progress_handlers($this)

	if (!number) number = 0
	if (!reverse) reverse = 0
	if (!speed) speed = parseInt($this.data('animation_speed'))
	if (!delay) delay = parseInt($this.data('delay'))

console.log('show: number ' + number + ' reverse ' + reverse + ' speed ' + speed + ' delay ' + delay);
	
	$this.data('drag_disabled', 'on');
	setTimeout(function(){
		$this.data('drag_disabled', 'off');
	}, speed);
	
	carousel_basic_user_hide($this);
	
	// move left
	var current_number = parseInt($('.carousel_basic_active', $this).data('carousel_basic_number'));
	var new_number = number;

	if (new_number != current_number){
	
		// if no over the end move
		if ($this.data('cycle') == 0){
		
			if ((reverse == 0 && current_number > new_number) || (reverse == 1 && current_number < new_number)){
				$('.carousel_basic_item_' + current_number, $this).animate({'left': '0'}, speed/5);
				return;
			}
		}
		
		carousel_basic_cycle_arrows($this, new_number)
		
		$('.carousel_basic_item_' + current_number + ',.carousel_basic_item_' + new_number, $this).finish();
		
		$('.carousel_basic_button_' + current_number, $this).removeClass('carousel_basic_active');
		
		$('.carousel_basic_button_' + new_number, $this).addClass('carousel_basic_active');
		
		// move thingies
		$('.carousel_basic_item', $this).not('.carousel_basic_item_' + current_number + ',.carousel_basic_item_' + new_number).css({'z-index':'48'});
		$('.carousel_basic_item_' + current_number, $this).css({'z-index':'49'});
		var animate_width = $('.carousel_basic_item_' + current_number, $this).width();
		var starting_x = parseInt($('.carousel_basic_item_' + current_number, $this).css('left'));
		if (reverse == 0){
			$('.carousel_basic_item_' + new_number, $this).css({'left': animate_width + starting_x + 'px', 'z-index':'50', 'display':'block'});
			$('.carousel_basic_item_' + new_number, $this).animate({'left':'0px'}, speed);
			$('.carousel_basic_item_' + current_number, $this).animate({'left': '-' + animate_width + 'px'}, speed);
		} else {
			$('.carousel_basic_item_' + new_number, $this).css({'left': - animate_width + starting_x + 'px', 'z-index':'50', 'display':'block'});
			$('.carousel_basic_item_' + new_number, $this).animate({'left':'0px'}, speed);
			$('.carousel_basic_item_' + current_number, $this).animate({'left': animate_width + 'px'}, speed);
		}
	
	}
	
	var $video = $('.carousel_basic_item_video_video', $('.carousel_basic_item_' + new_number, $this));
	if ($video.length){
		$video.get(0).muted = true; // false;
console.log('show - has video');
	}

	carousel_basic_user_show($this)
	
	if (!$video.length){

console.log('set handler - show', delay);

		$this.data('play_handler', setTimeout(function(){
			carousel_basic_next($this);
		}, delay));
	}
	
}

function carousel_basic_sound_on(){
	
	'use strict'

	$('.carousel_basic_item_video_visible', $('.carousel_basic_item_' + $('.carousel_basic_active').data('carousel_basic_number'))).get(0).muted = true; // false;

}

function carousel_basic_sound_off(){
	
	'use strict'

	$('.carousel_basic_item_video_visible', $('.carousel_basic_item_' + $('.carousel_basic_active').data('carousel_basic_number'))).get(0).muted = true;

}

$(document).ready(function(){

	'use strict'

	$(window).on('resize.cms', function(){
		carousel_basic_resize();
	});

	carousel_basic_init();
	
	carousel_basic_resize();

})
