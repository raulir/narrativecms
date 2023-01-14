<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=<?= 
			!empty($GLOBALS['config']['mobile_zoom']) ? 'yes' : 'no' ?>">
<style type="text/css">
	html {font-size: <?= 100/$GLOBALS['config']['rem_k'] ?>v<?= empty($GLOBALS['config']['rem_switched']) ? 'w' : 'h' ?> !important;}
<?php if(!empty($GLOBALS['config']['rem_px']) && empty($GLOBALS['config']['rem_switched'])): // stop growing at some point ?>
	@media (min-width: <?= $GLOBALS['config']['rem_px'] + 1 ?>px) { 
	html {font-size: <?= $GLOBALS['config']['rem_px']/$GLOBALS['config']['rem_k'] ?>px !important;}
	}
<?php endif ?><?php if(!empty($GLOBALS['config']['rem_px'])): // mobile - 1/50 ?>
	@media (max-width: <?= $GLOBALS['config']['rem_m_px'] ?>px) {
	html {font-size: <?= 100/$GLOBALS['config']['rem_m_k'] ?>v<?= empty($GLOBALS['config']['rem_switched']) ? 'w' : 'h' ?> !important;}
	}
<?php endif ?>
</style>
<script type="text/javascript">
	var config_url = '<?= $GLOBALS['config']['base_url'] ?>';
	<?= !empty($_SESSION['cms_user']['cms_user_id']) ? 'var admin_logged_in = 1;' : '' ?>
</script>
</head>

<body>

	<?= get_position('header', $data) ?>

	<?= get_position('main', $data) ?>

	<?= get_position('footer', $data) ?>
	
</body>
</html>