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
			_ib($GLOBALS['config']['cms_background'], ['width' => 1000, 'css' => 'font-size: 9.8px; ']) : 
			print(' style="font-size: 9.8px; " ') ?>>
	
		<div class="admin_container">
			
			<div class="cms_header_content">
				<?php print(get_position('header', $data)); ?>
				<div style="clear: both; "><!--  --></div>
			</div>
			
			<div class="cms_content">
		
				<?php print(get_position('main', $data)); ?>
	
				<div style="clear: both; "><!--  --></div>
	
			</div>
			
		</div>
		
	</body>
</html>
