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

	<?php _panel('cms/cms_rem') ?>

</body>
</html>