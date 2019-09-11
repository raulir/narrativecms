<script>

	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	
<?php if(!empty($GLOBALS['config']['analytics']) && !empty($ids) && is_array($ids) && count($ids)): ?>
	<?php foreach($ids as $id): ?>
	
		gtag('config', '<?= $id['gtag_id'] ?>');

	<?php endforeach ?>
<?php endif ?>

	setTimeout(function(){
		
		injectScript('https://www.googletagmanager.com/gtag/js?id=<?= $id['gtag_id'] ?>');
	
	}, <?= !empty($delay) ? $delay : 0 ?>)

</script>
