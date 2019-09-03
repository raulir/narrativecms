<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
   		<meta name="viewport" content="width=1000,user-scalable=no">
		<script type="text/javascript">
 			var config_url = '<?php print($GLOBALS['config']['base_url']); ?>';
		</script>
   	</head>
	<body <?php (!empty($GLOBALS['config']['cms_background'])) ? 
			_ib($GLOBALS['config']['cms_background'], ['width' => 1400, 'css' => 'font-size: 10px; ']) : 
			_ib('cms/cms_background.jpg', ['width' => 1000, 'css' => 'font-size: 10px; background-size: 1000px; ']) ?>>
	
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
