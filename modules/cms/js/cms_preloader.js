
function cms_preloader_class(){
	
	this.preloaded_images = [];
	
	this.preloaded_videos = [];
	this.important_videos = [];
	
	this.preloaded_sounds = [];

	this.images_ok = false
	this.videos_ok = false
	this.sounds_ok = false

	this.preload = function(params) {

		return new Promise ((resolve, reject) => {

			var _this = this;
				
			var ok_interval = setInterval(() => {

				if (this.images_ok && this.videos_ok && this.sounds_ok){
					clearInterval(ok_interval)
					params.images = this.preloaded_images 
					params.videos = this.preloaded_videos
					params.sounds = this.preloaded_sounds 
					resolve(params)
				}
				
			}, 100)
			
			if (typeof params.images == 'undefined'){
				_this.images_ok = true
			} else {
				
				for (var i = 0; i < params.images.length; i++) {
					this.preloaded_images[i] = new Image();
					this.preloaded_images[i].src = params.images[i];
				}
				
				var _this = this;
				var interval = setInterval(function(){
					if (_this.count_not_complete() == 0){
						clearInterval(interval);
						_this.images_ok = true
					}
				}, 100);
	
			}
	
			if (typeof params.videos != 'undefined'){
				
				
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

					_this.video_interval = setInterval(function(){
						var ret = _this.check_videos()
						if (!ret) {
							clearInterval(_this.video_interval)
							_this.videos_ok = true
						}
					}, 200);

				}
				
			} else {
				_this.videos_ok = true
			}

			if (typeof params.sounds != 'undefined'){
				
				var _this = this;
				
				// add to sounds to load
				$(params.sounds).each(function(key, sound){
					// check if this sound is in preloaded sounds
					var is = 0;
					for (var i = 0; i < _this.preloaded_sounds.length; i++) {
						if (_this.preloaded_sounds[i].src == sound){
							is = 1;
						}
					}
					// add
					if (is == 0){
						_this.preloaded_sounds.push({'src':sound, 'complete':0})
					}
					
				});
	
				if (typeof this.sound_interval == 'undefined'){
					var _this = this;
					_this.sound_interval = setInterval(function(){
						var ret = _this.check_sounds()
						if (!ret) {
							clearInterval(_this.sound_interval)
							_this.sounds_ok = true
						}
					}, 100);
				}
				
			} else {
				_this.sounds_ok = true
			}

		})
			
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
			
			this.preloaded_videos[next_key].complete = -1;
			this.preloaded_videos[next_key].req = new XMLHttpRequest()
			
			this.preloaded_videos[next_key].req.open('GET', this.preloaded_videos[next_key].src, true);
			this.preloaded_videos[next_key].req.responseType = 'blob';

			this.preloaded_videos[next_key].req.onload = function() {
			   if (this.status === 200) {

				   _preloader.preloaded_videos[next_key].complete = 1

				   var videoBlob = this.response;
				   
				   var vid = URL.createObjectURL(videoBlob)

				   var $video_element = $(_preloader.preloaded_videos[next_key].target).children('video')
				   
				   $video_element.get(0).src = _preloader.preloaded_videos[next_key].src
				   // vid
			   }
			}
			this.preloaded_videos[next_key].req.onerror = function() {

			}

			this.preloaded_videos[next_key].req.send()

		}

		if (loading == 0 && next_key == -1) {
			return false
		} else {
			return true
		}
		
	}

	this.check_sounds = function(){
		
		var _preloader = this;
		
		var loading = 0;
		var next_key = -1;
		$(this.preloaded_sounds).each(function(key, sound){
			if (sound.complete == -1){
				loading = loading + 1;
			}
			if (next_key == -1 && sound.complete == 0){
				next_key = key;
			}
		});
		
		if (loading < 2 && next_key != -1){
			
			this.preloaded_sounds[next_key].complete = -1;
			this.preloaded_sounds[next_key].req = new XMLHttpRequest()
			
			this.preloaded_sounds[next_key].req.open('GET', this.preloaded_sounds[next_key].src, true);
			this.preloaded_sounds[next_key].req.responseType = 'blob';

			this.preloaded_sounds[next_key].req.onload = function() {
			   if (this.status === 200) {

				   _preloader.preloaded_sounds[next_key].complete = 1

			   }
			}
			this.preloaded_sounds[next_key].req.onerror = function() {

			}

			this.preloaded_sounds[next_key].req.send()

		}

		if (loading == 0 && next_key == -1) {
			return false
		} else {
			return true
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

var cms_preloader = new cms_preloader_class();
