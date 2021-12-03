<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=600,user-scalable=no">
	<script type="text/javascript">

		setTimeout(() => {

			const _cms_rem = Number(window.getComputedStyle(document.body).getPropertyValue('font-size').match(/\d+/)[0])

			if (typeof window.orientation == 'undefined'){
				var a_width = window.innerWidth;
				var a_height = window.innerHeight;
			} else {
				var a_width = document.documentElement.clientWidth;
				var a_height = document.documentElement.clientHeight;
			}
			
			if (a_width > <?= !empty($GLOBALS['config']['rem_m_px']) ? $GLOBALS['config']['rem_m_px'] : '0' ?>){
				_cms_mobile = false;
			} else {
				_cms_mobile = true;
			}
			
		}, 0)

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