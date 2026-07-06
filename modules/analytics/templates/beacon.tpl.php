<?php if (!empty($show)): ?>
<?php if (!empty($js_tracking)): ?>
<div class="analytics_beacon_container analytics_beacon_content"
	data-js_tracking="1"
	data-php_tracking="<?= !empty($php_tracking) ? '1' : '0' ?>"
	data-delay="<?= (int)($delay ?? 0) ?>"
	data-collect_engagement="<?= !empty($collect_engagement) ? '1' : '0' ?>"
	<?php if (!empty($beacon_id)): ?>data-beacon_id="<?= htmlentities($beacon_id) ?>"<?php endif ?>>
</div>
<?php elseif (!empty($php_tracking)): ?>
<span class="analytics_beacon_container analytics_beacon_content" hidden
	data-js_tracking="0"
	data-php_tracking="1"
	<?php if (!empty($beacon_id)): ?>data-beacon_id="<?= htmlentities($beacon_id) ?>"<?php endif ?>></span>
<?php endif ?>
<?php endif ?>