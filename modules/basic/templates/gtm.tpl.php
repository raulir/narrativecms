<?php if(!empty($GLOBALS['config']['analytics']) && !empty($settings['gtm_id'])): ?>

<div class="gtm_container" data-gtm_id="<?= $settings['gtm_id'] ?>">

	<!-- Google Tag Manager (noscript) -->
	
	<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $settings['gtm_id'] ?>"
	height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
	
	<!-- End Google Tag Manager (noscript) -->

</div>
	
<?php endif ?>