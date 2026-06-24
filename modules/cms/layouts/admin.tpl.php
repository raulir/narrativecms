<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
   		<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
   		<style type="text/css">
			html {font-size: 1vw !important;}
			@media (min-width: 1001px) {
				html {font-size: 10px !important;}
			}
		</style>
		<script type="text/javascript">
			var _cms_base = '<?php print($GLOBALS['config']['base_url']); ?>'
			setInterval(() => {_cms_rem = parseFloat(getComputedStyle(document.documentElement).fontSize)}, 1000)
			var _cms_rem = parseFloat(getComputedStyle(document.documentElement).fontSize)
			var _cms_mobile = false
		</script>
   	</head>
	<body <?php (!empty($GLOBALS['config']['cms_background'])) ? 
			_ib($GLOBALS['config']['cms_background'], 1400) : _ib('cms/cms_background.jpg', ['width' => 1000, 'css' => 'background-size: 100.0rem; ']) ?>>
	
		<div class="cms_header_container">
			<div class="cms_header_content">
				<div class="cms_header_area">
					<?= get_position('header', $data) ?>
				</div>
			</div>
		</div>

		<div class="cms_admin_container">

			<div class="cms_admin_content">
				<?= get_position('main', $data) ?>
			</div>
			
		</div>
		
	</body>
</html>
