Redirecting in 5s ...
<script type="text/javascript">
	setTimeout(() => {window.location.href = "<?= (stristr($target_page['url'], 'http') ? '' : $GLOBALS['config']['base_url']).$target_page['url'] 
			?><?= $hash_needed ? ('#'.$hash) : '' ?>"}, 5000);
</script>
