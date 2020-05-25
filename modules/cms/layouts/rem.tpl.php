<!DOCTYPE html>
<html<?= !empty($_COOKIE['rem']) ? ' style="font-size: '.$_COOKIE['rem'].'px; "' : '' ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=<?= 
			!empty($GLOBALS['config']['mobile_zoom']) ? 'yes' : 'no' ?>">
	<script type="text/javascript">
		var config_url = '<?= $GLOBALS['config']['base_url'] ?>';
		<?php if(!empty($_SESSION['cms_user']['cms_user_id'])): ?>var admin_logged_in = 1;<?php endif ?>
	</script>
</head>

<body>

	<?= get_position('header', $data) ?>

	<?= get_position('main', $data) ?>

	<?= get_position('footer', $data) ?>

	<script type="text/javascript">

		var _cms_rem = 10;
		var _cms_mobile = false;
	
		function _set_rem(){
	
			<?php if(empty($GLOBALS['config']['rem_switched'])): ?>
				var width = document.documentElement.clientWidth;
				var height = document.documentElement.clientHeight;
				var real_width = width;
			<?php else: ?>
				var width = document.documentElement.clientHeight;
				var height = document.documentElement.clientWidth;
				var real_width = height;
			<?php endif ?>
	
			if (real_width > <?= !empty($GLOBALS['config']['rem_m_px']) ? $GLOBALS['config']['rem_m_px'] : '0' ?>){
	
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
				
				var rem = Math.round(real_width/<?= !empty($GLOBALS['config']['rem_m_k']) ? $GLOBALS['config']['rem_m_k'] : '50' ?> * 100)/100;
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
					
					document.getElementById('debug_event').innerHTML = document.getElementById('debug_event').innerHTML + ' orientation: ' + orientation;
					setTimeout(function(){ 
						document.getElementById('debug_event').innerHTML = '';
					}, 5000);
					
				}, 30); 
			} 
		};

		if (typeof screen.orientation != 'undefined' && typeof screen.orientation.addEventListener != 'undefined')
			screen.orientation.addEventListener('change', _orientationchange); 
    	else 
        	window.addEventListener('orientationchange', _orientationchange);

    	window.addEventListener('load', _orientationchange);

	</script>
	
</body>
</html>