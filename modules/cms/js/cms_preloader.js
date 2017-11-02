
function preloader_class(){
	
	this.preloaded_images = [];
	
	this.preloaded_videos = [];
	this.important_videos = [];

	this.preload = function(params) {
		
		if (typeof params.images != 'undefined'){
			
			for (var i = 0; i < params.images.length; i++) {
				this.preloaded_images[i] = new Image();
				this.preloaded_images[i].src = params.images[i];
			}
			
			if (typeof params.after != 'undefined'){
				var that = this;
				var interval = setInterval(function(){
					if (that.count_not_complete() == 0){
						clearInterval(interval);
						params.after(that.preloaded_images);
					}
				}, 100);
			}
			
			if (typeof params.after_each != 'undefined'){
				var that = this;
				var interval_each = setInterval(function(){

					if (that.count_not_complete() == 0){
						setTimeout(function(){
							clearInterval(interval_each);
						}, 1000)
					}

					for (var i = 0; i < that.preloaded_images.length; i++) {
						if (that.preloaded_images[i].complete && typeof that.preloaded_images[i].cms_after == 'undefined'){
							that.preloaded_images[i].cms_after = 1;
							params.after_each(that.preloaded_images[i]);
						}
					}

				}, 500);
			}

		}

		if (typeof params.videos != 'undefined'){
			
			var _this = this;
			
			// add to videos to load
			$(params.videos).each(function(key, video){
				// check if this video is in preloaded videos
				var is = 0;
				for (var i = 0; i < _this.preloaded_videos.length; i++) {
					if (_this.preloaded_videos[i].src == video.src){
						is = 1;
					}
				}
				// add
				if (is == 0){
					_this.preloaded_videos.push({'src':video.src, 'target':video.target, 'type':video.type, 'complete':0})
				}
				
			});
			
			if (typeof this.video_interval == 'undefined'){
				var _this = this;
				_this.video_interval = setInterval(function(){
					_this.check_videos();
				}, 100);
			}
			
		}
	}
	
	this.check_videos = function(){
		
		var _preloader = this;
		
		var loading = 0;
		var next_key = -1;
		$(this.preloaded_videos).each(function(key, video){
			if (video.complete == -1){
				loading = loading + 1;
			}
			if (next_key == -1 && video.complete == 0){
				next_key = key;
			}
		});
		
		if (loading < 2 && next_key != -1){
			// start loading next - create video in background and start playing
			var _video = document.createElement('video');
			this.preloaded_videos[next_key].complete = -1;
			_video.src = this.preloaded_videos[next_key].src;
			_video.muted = true; 

			_video.addEventListener('canplaythrough', function() {
	        	
//				document.body.appendChild(this);
//                this.parentNode.removeChild(this);
                _preloader.preloaded_videos[next_key].complete = 1;
                
console.log('loaded ' + next_key + ' ' + _preloader.preloaded_videos[next_key].target + ' ' + _preloader.preloaded_videos[next_key].src);

				var $video_element = $(_preloader.preloaded_videos[next_key].target).children('video');
// console.log('target ' + $video_element.length);
				
				if($video_element.length > 0){
					$video_element.html($video_element.html() + '<source src="' + _preloader.preloaded_videos[next_key].src + 
							'" type="video/' + _preloader.preloaded_videos[next_key].type + '"></source>');
 //					$video_element[0].load();
					
					if($video_element[0].readyState > 0){
						$video_element[0].currentTime = 0;
					} else {
						$video_element.off('loadedmetadata.cms').on('loadedmetadata.cms', function(){
							$(this).get(0).currentTime = 0;
						});
					}
//					$video_element[0].currentTime = 0;
				}
				
			}, false);

			_video.addEventListener('timeupdate', function() {
			    if (this.currentTime > 0) {
			        this.pause();
			    }
			});
	        
			_video.play();
			
		}
		
	}

	this.count_not_complete = function(){
		var ret = 0;
		for (var i = 0; i < this.preloaded_images.length; i++) {
			if (!this.preloaded_images[i].complete){
				ret = ret + 1;
			}
		}
		return ret;
	}

}
