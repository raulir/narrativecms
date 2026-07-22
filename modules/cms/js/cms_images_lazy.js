
// Long-standing CMS feature: B/W original → image_resize → colour optimised thumb.
// Do not break when force_image_download already puts ?v= on result.src.
// On resize failure: stop for this page load; try again only on next page load.

( function( $ ) {
	
    $.fn.cms_images_lazy = function(params) {
    	
    	params = params || {};
    	
    	var $set = $(this);
		
		$set.each(function(){
			
			var $this = $(this);
			
			// Skip already finished or failed this page load
			if ($this.hasClass('cms_images_lazy_done') || $this.hasClass('cms_images_lazy_failed')){
				return
			}

			if (!$this.hasClass('cms_images_lazy_ok')){

				$this.addClass('cms_images_lazy_ok').addClass('cms_images_lazy_waiting');

			}
			
		});
		
		if (window._cms_images_lazy_interval){
			clearInterval(window._cms_images_lazy_interval)
			window._cms_images_lazy_interval = null
		}

		window._cms_images_lazy_interval = setInterval(function(){
			if ($('.cms_images_lazy_loading').length < 1){
				var $next = $('.cms_images_lazy_waiting').not('.cms_images_lazy_done').not('.cms_images_lazy_failed').first();
				if ($next.length){
					$next.addClass('cms_images_lazy_loading').removeClass('cms_images_lazy_waiting');
					cms_images_lazy_next($next);
				} else {
					clearInterval(window._cms_images_lazy_interval);
					window._cms_images_lazy_interval = null
				}
			}
		}, 500);

    };
    
}( jQuery ));

/**
 * Append cache-bust only when missing.
 * force_image_download may already put ?v=hash on src — never create path?v=a?v=b
 */
function cms_images_lazy_src_with_version(src, $el){

	if (!src){
		return ''
	}

	if (src.indexOf('?') !== -1 || src.indexOf('&v=') !== -1){
		return src
	}

	var v = ''
	if ($el && $el.length){
		v = $el.attr('data-v') || ''
	}
	if (v === '' || v === undefined || v === null){
		v = Math.floor(Date.now() / 1000)
	}

	return src + '?v=' + encodeURIComponent(v)

}

/** Stop queueing for this element until next full page load */
function cms_images_lazy_stop($this){

	$this.addClass('cms_images_lazy_done cms_images_lazy_failed')
	$this.removeClass('cms_images_lazy_loading cms_images_lazy_waiting')

}

function cms_images_lazy_next($this){

	get_api('cms/image_resize', {
		'do': 'resize',
		'width': $this.data('w1'),
		'output': $this.data('output'),
		'name': $this.data('cms_images_lazy'),
		'success': function(data){

			var result = (data && data.result) ? data.result : {}

			// Hard fail (permissions, write error): keep interim original, try again next page load only
			if (result.failed){
				cms_images_lazy_stop($this)
				return
			}

			var src = result.src ? result.src : ''

			// Locked / busy: short re-queue, then give up for this page load
			if (!src || result.busy){
				var tries = parseInt($this.data('cms_images_lazy_busy_tries'), 10) || 0
				tries++
				$this.data('cms_images_lazy_busy_tries', tries)
				if (tries >= 8){
					cms_images_lazy_stop($this)
					return
				}
				$this.removeClass('cms_images_lazy_loading').addClass('cms_images_lazy_waiting')
				return
			}

			src = cms_images_lazy_src_with_version(src, $this)
			$this.css({'background-image': 'url(' + src + ')'})
			$this.removeClass('cms_images_lazy_waiting cms_images_lazy_loading')

			if ($this.data('w2')){

				setTimeout(function(){

					get_api('cms/image_resize', {
						'do': 'resize',
						'width': $this.data('w2'),
						'output': $this.data('output'),
						'name': $this.data('cms_images_lazy'),
						'success': function(data2){

							var result2 = (data2 && data2.result) ? data2.result : {}
							if (result2.failed || result2.busy || !(result2.src)){
								// 1x already applied; do not loop on 2x failure
								cms_images_lazy_stop($this)
								return
							}

							$this.addClass('cms_images_lazy_done')
							$this.removeClass('cms_images_lazy_loading cms_images_lazy_waiting cms_images_lazy_failed')

						},
						'error': function(){
							cms_images_lazy_stop($this)
						}
					})

				}, 250)

			} else {

				$this.addClass('cms_images_lazy_done')
				$this.removeClass('cms_images_lazy_loading cms_images_lazy_waiting')

			}

		},
		'error': function(){
			// Network / non-JSON (e.g. PHP fatal): do not re-queue this page load
			cms_images_lazy_stop($this)
		}
	})

}

function cms_images_lazy_init($root){

	var $scope
	if ($root && $root.length){
		$scope = $root.find('[data-cms_images_lazy]')
		if ($root.is('[data-cms_images_lazy]')){
			$scope = $scope.add($root)
		}
	} else {
		$scope = $('[data-cms_images_lazy]')
	}

	if (!$scope.length){
		return
	}

	// delay so B/W original paints first
	setTimeout(function(){

		$scope.cms_images_lazy()

	}, 100)

}

$(document).ready(function() {

	cms_images_lazy_init()

})
