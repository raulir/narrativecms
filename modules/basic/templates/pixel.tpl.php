<?php if(!empty($GLOBALS['config']['analytics']) && !empty($settings['pixel_id'])): ?>
	<?php // Consent is checked in the browser (pixel.js basic_tracking_allowed): this html may come from the page cache ?>
	<div class="pixel_container" data-pixel_id="<?= $settings['pixel_id'] ?>" data-delay="<?= !empty($delay) ? $delay : 0 ?>" data-consent="<?= in_array('gdpr', $GLOBALS['config']['modules'] ?? [], true) ? 1 : 0 ?>"></div>
<?php endif ?>
