<!DOCTYPE html>
<html<?= !empty($_COOKIE['rem']) ? ' style="font-size: '.$_COOKIE['rem'].'px; "' : '' ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=<?= !empty($_COOKIE['width']) ? $_COOKIE['width'] : '600' ?>,user-scalable=no">
	<script type="text/javascript">

		var _old_orientation = <?= !empty($_COOKIE['orientation']) ? $_COOKIE['orientation'] : -1 ?>;

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

        	var viewport = document.querySelector('meta[name=viewport]');
// console.log(_old_orientation + ' ' + orientation);        	
        	if (orientation != _old_orientation || _old_orientation == -1){
// console.log(_old_orientation + ' != ' + orientation);        	

				_old_orientation = orientation;
				document.cookie = 'orientation=' + encodeURIComponent(orientation) + '; path=/';

        	   	document.body.style.display='none';
        		viewport.setAttribute('content', '');
				setTimeout(function(){

					var width = orientation ? 900 : 600;
					if (screen.width >= 768){
						width = 1024;
					}
										
					viewport.setAttribute('content', 'width=' + width + ',user-scalable=no');
					
					document.cookie = 'width=' + width + '; path=/';
					
					setTimeout(function(){
						
				    	document.body.offsetHeight;

				    	window.dispatchEvent(new Event('resize'));
				    	document.body.style.removeProperty('display');
				    	
					}, 20);
					
				}, 250);

        	}

		};
		
    	if (typeof screen.orientation != 'undefined' && typeof screen.orientation.addEventListener != 'undefined') screen.orientation.addEventListener('change', _orientationchange); 
    	else window.addEventListener('orientationchange', _orientationchange);

    	window.addEventListener('load', _orientationchange);
    	
		var config_url = '<?php print($GLOBALS['config']['base_url']); ?>';
		<?php if(!empty($_SESSION['cms_user']['cms_user_id'])): ?>var admin_logged_in = 1;<?php endif ?>

	</script>
</head>

<body>

	<script type="text/javascript">

		function _set_rem(){
			var width = window.innerWidth;
			var height = window.innerHeight;
			if (width > <?= !empty($GLOBALS['config']['rem_m_px']) ? $GLOBALS['config']['rem_m_px'] : '0' ?>){

				var rem_ratio = <?= !empty($GLOBALS['config']['rem_ratio']) ? $GLOBALS['config']['rem_ratio'] : '100' ?>;
				var rem_px = <?= !empty($GLOBALS['config']['rem_px']) ? $GLOBALS['config']['rem_px'] : '1000000' ?>;

				if (width > rem_px){
					width = rem_px;
				}

				if (width > height * rem_ratio){
					width = Math.floor(height * rem_ratio);
				}

				var rem = Math.round(width/100 * 100)/100;
			} else {
				var rem = Math.round(width/50 * 100)/100;
			}
			$('html').css('font-size', rem + 'px');
			document.cookie = 'rem=' + encodeURIComponent(rem) + '; path=/';
		}

		_set_rem();

		window.addEventListener('resize', _set_rem);
		
	</script>

	<?php print(get_position('header', $data)); ?>

	<?php print(get_position('main', $data)); ?>

	<?php print(get_position('footer', $data)); ?>

</body>
</html>