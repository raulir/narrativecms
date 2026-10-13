<?php
$rem_k = max(1, (int)($GLOBALS['config']['rem_k'] ?? 100));
$rem_m_k = max(1, (int)($GLOBALS['config']['rem_m_k'] ?? 50));
$rem_px = (int)($GLOBALS['config']['rem_px'] ?? 0);
$rem_m_px = (int)($GLOBALS['config']['rem_m_px'] ?? 0);
?>
<!DOCTYPE html>
<html lang="<?= cms_visitor_html_lang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=<?= 
			!empty($GLOBALS['config']['mobile_zoom']) ? 'yes' : 'no' ?>">
<style type="text/css">
	html {font-size: <?= 100/$rem_k ?>v<?= empty($GLOBALS['config']['rem_switched']) ? 'w' : 'h' ?> !important;}
<?php if($rem_px && empty($GLOBALS['config']['rem_switched'])): // stop growing at some point ?>
	@media (min-width: <?= $rem_px + 1 ?>px) { 
	html {font-size: <?= $rem_px/$rem_k ?>px !important;}
	}
<?php endif ?><?php if($rem_m_px): // mobile - 1/50 ?>
	@media (max-width: <?= $rem_m_px ?>px) {
	html {font-size: <?= 100/$rem_m_k ?>v<?= empty($GLOBALS['config']['rem_switched']) ? 'w' : 'h' ?> !important;}
	}
<?php endif ?>
</style>
<script type="text/javascript">
	var _cms_base = '<?= $GLOBALS['config']['base_url'] ?>';
	var _cms_login_url = <?= json_encode(_user_login_url(false)) ?>;
	var _cms_login_text = <?= json_encode(_user_login_text(false)) ?>;
	var config_url = _cms_base;
	<?= !empty($_SESSION['cms_user']['cms_user_id']) ? 'var admin_logged_in = 1;' : '' ?>
	var _cms_rem = parseFloat(getComputedStyle(document.documentElement).fontSize)
	var _cms_mobile_query = window.matchMedia('(max-width: <?= $rem_m_px ?>px)')
	var _cms_mobile = _cms_mobile_query.matches
	function cms_mobile_update(){
		_cms_mobile = _cms_mobile_query.matches
		_cms_rem = parseFloat(getComputedStyle(document.documentElement).fontSize)
	}
	if (_cms_mobile_query.addEventListener){
		_cms_mobile_query.addEventListener('change', cms_mobile_update)
	} else if (_cms_mobile_query.addListener){
		_cms_mobile_query.addListener(cms_mobile_update)
	}
	window.addEventListener('resize', cms_mobile_update)
	window.addEventListener('orientationchange', cms_mobile_update)
</script>
</head>

<body>

	<?= get_position('header', $data) ?>

	<?= get_position('main', $data) ?>

	<?= get_position('footer', $data) ?>
	
</body>
</html>