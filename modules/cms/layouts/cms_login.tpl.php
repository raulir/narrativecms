<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
   		<meta name="viewport" content="width=1000,user-scalable=no">
		<script type="text/javascript">
			var config_url = '<?php print($GLOBALS['config']['base_url']); ?>';
		</script>
	</head>
	<body <?php if (!empty($GLOBALS['config']['cms_background'])) _ib($GLOBALS['config']['cms_background']); ?>>
	
		<?php print(get_position('main', $data)); ?>
	
	</body>
</html>
