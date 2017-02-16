<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<script type="text/javascript">
		var _orientation = function(){if (typeof window.orientation != 'undefined') return (window.orientation == 0 || window.orientation == 180) ? 900 : 1024;
				else return screen.width > 768 ? 1024 : 900;}
    	document.write('<meta name="viewport" content="width='+(screen.width>=768?_orientation():'600')+',user-scalable=no">');
    	var _orientationchange = function(){
        	var viewport = document.querySelector('meta[name=viewport]');
        	var orientation = _orientation();
        	document.body.style.display='none';
        	viewport.setAttribute('content', '');
			setTimeout(function(){
				viewport.setAttribute('content', 'width='+(screen.width>=768?orientation:'600')+',user-scalable=no');
				setTimeout(function(){
				    document.body.offsetHeight;
				    document.body.style.removeProperty('display');
				}, 20);
			}, 500);
		};
    	if (typeof screen.orientation != 'undefined' && typeof screen.orientation.addEventListener != 'undefined') screen.orientation.addEventListener('change', _orientationchange); 
    	else window.addEventListener('orientationchange', _orientationchange);
		var config_url = '<?php print($GLOBALS['config']['base_url']); ?>';
		<?php if(!empty($_SESSION['cms_user']['cms_user_id'])): ?>var admin_logged_in = 1;<?php endif ?>
	</script>
</head>

<body>

	<?php print(get_position('header', $data)); ?>

	<?php print(get_position('main', $data)); ?>

	<?php print(get_position('footer', $data)); ?>

</body>
</html>