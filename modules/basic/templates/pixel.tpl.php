<?php if(!empty($GLOBALS['config']['analytics']) && !empty($settings['pixel_id'])): ?>
	<div class="pixel_container" data-pixel_id="<?= $settings['pixel_id'] ?>" data-delay="<?= !empty($delay) ? $delay : 0 ?>"></div>
<?php endif ?>