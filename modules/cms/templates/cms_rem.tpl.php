	<script type="text/javascript">

		var _cms_rem = 10;
		var _cms_mobile = false;
	
		function _set_rem(){

			if (typeof window.orientation == 'undefined'){
				var a_width = window.innerWidth;
				var a_height = window.innerHeight;
			} else {
				var a_width = document.documentElement.clientWidth;
				var a_height = document.documentElement.clientHeight;
			}
	
			<?php if(empty($GLOBALS['config']['rem_switched'])): ?>
				var width = a_width;
				var height = a_height;
			<?php else: ?>
				var width = a_height;
				var height = a_width;
			<?php endif ?>
	
			if (a_width > <?= !empty($GLOBALS['config']['rem_m_px']) ? $GLOBALS['config']['rem_m_px'] : '0' ?>){
	
				var rem_ratio = <?= !empty($GLOBALS['config']['rem_ratio']) ? $GLOBALS['config']['rem_ratio'] : '100' ?>;
				var rem_px = <?= !empty($GLOBALS['config']['rem_px']) ? $GLOBALS['config']['rem_px'] : '1000000' ?>;
	
				if (width > rem_px){
					width = rem_px;
				}
	
				if (width > height * rem_ratio){
					width = Math.floor(height * rem_ratio);
				}
	
				var rem = Math.round(width/<?= !empty($GLOBALS['config']['rem_k']) ? $GLOBALS['config']['rem_k'] : '100' ?> * 100)/100;
				_cms_mobile = false;
				
			} else {
				
				var rem = Math.round(a_width/<?= !empty($GLOBALS['config']['rem_m_k']) ? $GLOBALS['config']['rem_m_k'] : '50' ?> * 100)/100;
				_cms_mobile = true;
	
			}
	
			document.body.parentNode.style.fontSize = rem + 'px';
			document.cookie = 'rem=' + encodeURIComponent(rem) + '; path=/';
	
			_cms_rem = rem;
	
		}
	
		_set_rem();
		
		setTimeout(_set_rem, 500);
		setTimeout(function(){
			_set_rem()
			setInterval(_set_rem, 10000);
		}, 1500);
	
		window.addEventListener('resize', _set_rem);

		var _old_orientation = <?= isset($_COOKIE['orientation']) ? $_COOKIE['orientation'] : -1 ?>;
		var _orientation = function(){ 
			if (typeof window.orientation != 'undefined') { 
				return (window.orientation == 0 || window.orientation == 180) ? 0 : 1; 
			} else if (screen.width > screen.height ) { 
				return 1; 
			} else { 
				return 0; 
			} 
		};
		
    	var _orientationchange = function(){ 
        	
        	var orientation = _orientation(); 
        	var changed = (orientation != _old_orientation); 
        	if (changed || _old_orientation == -1){ 
            	
            	var exp = new Date(); 
            	exp.setDate(exp.getDate() + 365); 
    			exp = exp.toUTCString(); 
    			_old_orientation = orientation; 
    			document.cookie = 'orientation=' + encodeURIComponent(orientation) + '; path=/; expires=' + exp; 
    			if (changed) {
    				document.body.parentNode.style.fontSize = '1px';
        			document.body.style.display='none';
					setTimeout(function(){ 
						document.body.style.display='block';
					}, 500);
        			
    			} 

    			setTimeout(function(){
        			
            		document.body.offsetHeight; 
            		window.dispatchEvent(new Event('resize')); 
            		
					_set_rem();

					setTimeout(_set_rem, 500);
					setTimeout(_set_rem, 1500);
					if(document.getElementById('debug_event')){
						document.getElementById('debug_event').innerHTML = document.getElementById('debug_event').innerHTML + ' orientation: ' + orientation;
						setTimeout(function(){ 
							document.getElementById('debug_event').innerHTML = '';
						}, 5000);
					}
					
				}, 30); 
			} 
		};

		if (typeof screen.orientation != 'undefined' && typeof screen.orientation.addEventListener != 'undefined')
			screen.orientation.addEventListener('change', _orientationchange); 
    	else 
        	window.addEventListener('orientationchange', _orientationchange);

    	window.addEventListener('load', _orientationchange);

	</script>