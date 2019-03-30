<?php if(!empty($GLOBALS['config']['analytics']) && !empty($settings['analytics_id'])): ?>
	<div class="analytics_container" data-analytics_id="<?= $settings['analytics_id'] ?>" data-delay="<?= !empty($delay) ? $delay : 0 ?>"></div>
<?php endif ?>